<?php

declare(strict_types=1);

use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\ScopeContextException;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

/**
 * @param array<string, list<string>> $axes
 * @param array<string, string> $defaults
 */
function makeScopeRegistry(array $axes = [], array $defaults = []): ScopeRegistryInterface
{
    return new class ($axes, $defaults) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        /** @param array<string, list<string>> $axes @param array<string, string> $defaults */
        public function __construct(
            private readonly array $axes,
            array $defaults = [],
        ) {
            $this->builtAxes = [];
            foreach ($axes as $name => $paths) {
                $default = $defaults[$name] ?? '__test_default';
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

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            return $this->getAxis($axisName)->hierarchy;
        }
    };
}

it('accepts a current scope for an axis via in and is fluent', function (): void {
    $registry = makeScopeRegistry(['geo' => ['eu', 'eu.de', 'us']]);
    $context = new ScopeContext($registry);

    $result = $context->in('geo', 'eu.de');

    expect($result)->toBe($context);
});

it('returns the current scope path for a set axis via get', function (): void {
    $registry = makeScopeRegistry(['geo' => ['eu', 'eu.de', 'us']]);
    $context = new ScopeContext($registry);
    $context->in('geo', 'eu.de');

    expect($context->get('geo'))->toBe('eu.de');
});

it('returns null from get for an unset axis', function (): void {
    $registry = makeScopeRegistry(['geo' => ['eu', 'eu.de', 'us']]);
    $context = new ScopeContext($registry);

    expect($context->get('geo'))->toBeNull();
});

it('throws UnknownAxisException when in is called with an unknown axis', function (): void {
    $registry = makeScopeRegistry(['geo' => ['eu', 'eu.de', 'us']]);
    $context = new ScopeContext($registry);

    expect(fn () => $context->in('locale', 'en'))->toThrow(UnknownAxisException::class);
});

it('throws ScopeContextException when in is called with a path not in the axis hierarchy', function (): void {
    $registry = makeScopeRegistry(['geo' => ['eu', 'eu.de', 'us']]);
    $context = new ScopeContext($registry);

    expect(fn () => $context->in('geo', 'eu.fr'))->toThrow(ScopeContextException::class);
});

it('clears a single axis via clear and all axes via clearAll', function (): void {
    $registry = makeScopeRegistry(['geo' => ['eu', 'eu.de', 'us'], 'locale' => ['en', 'fr']]);
    $context = new ScopeContext($registry);
    $context->in('geo', 'eu.de')->in('locale', 'en');

    $context->clear('geo');

    expect($context->get('geo'))->toBeNull()
        ->and($context->get('locale'))->toBe('en');

    $context->clearAll();

    expect($context->get('locale'))->toBeNull();
});

it(
    'exposes the full active-state map (axis-name → active-path) via the state() method, not just keys',
    function (): void {
        $registry = makeScopeRegistry(['geo' => ['eu', 'eu.de', 'us'], 'locale' => ['en', 'fr']]);
        $context = new ScopeContext($registry);
        $context->in('geo', 'eu.de')->in('locale', 'en');

        $state = $context->state();

        expect($state)->toBe(['geo' => 'eu.de', 'locale' => 'en']);
    },
);

it('returns an empty array when no axes are active', function (): void {
    $registry = makeScopeRegistry(['geo' => ['eu', 'eu.de']]);
    $context = new ScopeContext($registry);

    expect($context->state())->toBe([]);
});

it('returns a different map after the active path for an existing axis is changed via in()', function (): void {
    $registry = makeScopeRegistry(['geo' => ['eu', 'eu.de', 'eu.fr']]);
    $context = new ScopeContext($registry);
    $context->in('geo', 'eu.de');

    $before = $context->state();
    $context->in('geo', 'eu.fr');
    $after = $context->state();

    expect($before)->toBe(['geo' => 'eu.de'])
        ->and($after)->toBe(['geo' => 'eu.fr'])
        ->and($before)->not->toBe($after);
});

it('lists all axes currently set via activeAxes', function (): void {
    $registry = makeScopeRegistry(['geo' => ['eu', 'eu.de', 'us'], 'locale' => ['en', 'fr'], 'channel' => ['web']]);
    $context = new ScopeContext($registry);

    expect($context->activeAxes())->toBe([]);

    $context->in('geo', 'eu')->in('locale', 'en');

    expect($context->activeAxes())->toBe(['geo', 'locale']);

    $context->clear('geo');

    expect($context->activeAxes())->toBe(['locale']);
});
