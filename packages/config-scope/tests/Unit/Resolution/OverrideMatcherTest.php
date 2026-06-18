<?php

declare(strict_types=1);

use Markommerce\ConfigScope\Resolution\OverrideMatcher;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

/**
 * @param array<string, list<string>> $axes
 * @param array<string, string> $defaults
 */
function makeOverrideMatcherRegistry(array $axes = [], array $defaults = []): ScopeRegistryInterface
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

it('returns null from OverrideMatcher match when the overrides array is empty', function (): void {
    $registry = makeOverrideMatcherRegistry(['locale' => ['en']]);
    $context = new ScopeContext($registry);
    $context->in('locale', 'en');

    $enumerator = new SignatureCandidateEnumerator($registry);
    $matcher = new OverrideMatcher($enumerator);

    $result = $matcher->match([], ['locale'], $context);

    expect($result)->toBeNull();
});

it(
    'returns the matching override value from OverrideMatcher match when a signature matches the candidate list',
    function (): void {
        $registry = makeOverrideMatcherRegistry(['locale' => ['en']]);
        $context = new ScopeContext($registry);
        $context->in('locale', 'en');

        $enumerator = new SignatureCandidateEnumerator($registry);
        $matcher = new OverrideMatcher($enumerator);

        $overrides = ['locale:en' => 'override-value'];
        $result = $matcher->match($overrides, ['locale'], $context);

        expect($result)->toBe('override-value');
    },
);

it('returns null from OverrideMatcher match when no signature matches the current ScopeContext', function (): void {
    $registry = makeOverrideMatcherRegistry(['locale' => ['en', 'fr']]);
    $context = new ScopeContext($registry);
    $context->in('locale', 'en');

    $enumerator = new SignatureCandidateEnumerator($registry);
    $matcher = new OverrideMatcher($enumerator);

    // Override exists only for 'fr', context is 'en'
    $overrides = ['locale:fr' => 'french-value'];
    $result = $matcher->match($overrides, ['locale'], $context);

    expect($result)->toBeNull();
});
