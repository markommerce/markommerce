<?php

declare(strict_types=1);

use Markommerce\Config\Resolution\OverrideMatcher;
use Markommerce\Config\ValueObjects\ConfigRow;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

/**
 * Build a ScopeRegistryInterface that supports hierarchical dotted-path axes.
 *
 * @param array<string, list<string>> $axes axis name => list of scope paths (dotted)
 * @param array<string, string> $defaults axis name => default path (must be included in paths)
 */
function makeConfigRegistry(array $axes = [], array $defaults = []): ScopeRegistryInterface
{
    return new class ($axes, $defaults) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        /** @param array<string, list<string>> $axes @param array<string, string> $defaults */
        public function __construct(
            array $axes,
            array $defaults = [],
        ) {
            $this->builtAxes = [];
            foreach ($axes as $name => $paths) {
                $default = $defaults[$name] ?? 'default';
                if (!in_array($default, $paths, true)) {
                    $paths = array_merge([$default], $paths);
                }
                $hierarchy = new ScopeHierarchy($paths);
                $this->builtAxes[$name] = new ScopeAxis(name: $name, hierarchy: $hierarchy, default: $default);
            }
        }

        public function hasAxis(string $name): bool
        {
            return isset($this->builtAxes[$name]);
        }

        /** @throws UnknownAxisException */
        public function getAxis(string $name): ScopeAxis
        {
            if (!isset($this->builtAxes[$name])) {
                throw UnknownAxisException::forAxis($name);
            }

            return $this->builtAxes[$name];
        }

        /** @return list<string> */
        public function listAxes(): array
        {
            return array_keys($this->builtAxes);
        }

        /** @throws UnknownAxisException */
        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            return $this->getAxis($axisName)->hierarchy;
        }
    };
}

function makeRow(string $key = 'test/key', mixed $value = null, array $overrides = []): ConfigRow
{
    return new ConfigRow(key: $key, value: $value, overrides: $overrides, version: 1);
}

it('returns null when the row has no overrides', function (): void {
    $registry = makeConfigRegistry(['store' => ['eu', 'eu.de']]);
    $context = new ScopeContext($registry);
    $context->in('store', 'eu.de');

    $enumerator = new SignatureCandidateEnumerator($registry);
    $matcher = new OverrideMatcher($enumerator);

    $row = makeRow(overrides: []);

    expect($matcher->match($row, ['store'], $context))->toBeNull();
});

it('returns null when no candidate signature matches any override key', function (): void {
    $registry = makeConfigRegistry(['store' => ['eu', 'eu.de']]);
    $context = new ScopeContext($registry);
    $context->in('store', 'eu.de');

    $enumerator = new SignatureCandidateEnumerator($registry);
    $matcher = new OverrideMatcher($enumerator);

    // Override is for a completely different scope that won't be a candidate
    $row = makeRow(overrides: ['locale:en' => 'some-value']);

    expect($matcher->match($row, ['store'], $context))->toBeNull();
});

it('returns the override value for an exact context match on a single-axis property', function (): void {
    $registry = makeConfigRegistry(['store' => ['eu', 'eu.de']]);
    $context = new ScopeContext($registry);
    $context->in('store', 'eu.de');

    $enumerator = new SignatureCandidateEnumerator($registry);
    $matcher = new OverrideMatcher($enumerator);

    $row = makeRow(overrides: ['store:eu.de' => 42]);

    expect($matcher->match($row, ['store'], $context))->toBe(42);
});

it(
    'returns the most-specific composite override when both composite and single-axis overrides exist',
    function (): void {
        $registry = makeConfigRegistry([
            'channel' => ['b2b', 'b2c'],
            'locale'  => ['en', 'en.gb'],
        ]);
        $context = new ScopeContext($registry);
        $context->in('channel', 'b2b')->in('locale', 'en');

        $enumerator = new SignatureCandidateEnumerator($registry);
        $matcher = new OverrideMatcher($enumerator);

        $row = makeRow(overrides: [
            'channel:b2b'           => 'b2b-value',
            'locale:en'             => 'en-value',
            'channel:b2b|locale:en' => 'composite-value',
        ]);

        expect($matcher->match($row, ['channel', 'locale'], $context))->toBe('composite-value');
    },
);

it(
    'walks axis hierarchy via the candidate enumerator so a parent-scope override matches when no exact-leaf override exists',
    function (): void {
        $registry = makeConfigRegistry(['store' => ['eu', 'eu.de']]);
        $context = new ScopeContext($registry);
        // Context is at the leaf scope eu.de
        $context->in('store', 'eu.de');

        $enumerator = new SignatureCandidateEnumerator($registry);
        $matcher = new OverrideMatcher($enumerator);

        // Only the parent scope override exists, not the leaf
        $row = makeRow(overrides: ['store:eu' => 'eu-value']);

        expect($matcher->match($row, ['store'], $context))->toBe('eu-value');
    },
);

it('ignores overrides whose signature references axes not declared on the property', function (): void {
    $registry = makeConfigRegistry([
        'store'  => ['eu', 'eu.de'],
        'locale' => ['en', 'en.gb'],
    ]);
    $context = new ScopeContext($registry);
    $context->in('store', 'eu.de')->in('locale', 'en.gb');

    $enumerator = new SignatureCandidateEnumerator($registry);
    $matcher = new OverrideMatcher($enumerator);

    // Only the locale axis is in the overrides, but the property only declares 'store'
    $row = makeRow(overrides: ['locale:en.gb' => 'gb-value']);

    // locale axis is not declared on the property axes ['store'], so it must be ignored
    expect($matcher->match($row, ['store'], $context))->toBeNull();
});
