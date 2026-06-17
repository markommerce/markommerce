<?php

declare(strict_types=1);

use Markommerce\Indexer\ServedScopes\CartesianServedScopesProvider;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Signature\ScopeSignature;

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * @param array<string, list<string>> $axesMap axis name => all paths (including default)
 * @param array<string, string> $defaults axis name => default path
 */
function makeServedScopesRegistry(array $axesMap = [], array $defaults = []): ScopeRegistryInterface
{
    return new class ($axesMap, $defaults) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        /** @param array<string, list<string>> $axesMap @param array<string, string> $defaults */
        public function __construct(
            array $axesMap,
            array $defaults = [],
        )
        {
            $this->builtAxes = [];
            foreach ($axesMap as $name => $paths) {
                $default = $defaults[$name] ?? '__default';
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

// ─── Tests ───────────────────────────────────────────────────────────────────

it('returns an empty signature list when given no axes', function (): void {
    $registry = makeServedScopesRegistry(['locale' => ['en', 'fr']]);
    $provider = new CartesianServedScopesProvider($registry);

    $result = $provider->signatures([]);

    expect($result)->toBe([]);
});

it('returns one signature per non-default path for a single given axis', function (): void {
    $registry = makeServedScopesRegistry(
        axesMap: ['locale' => ['__default', 'en', 'fr']],
        defaults: ['locale' => '__default'],
    );
    $provider = new CartesianServedScopesProvider($registry);

    $result = $provider->signatures(['locale']);

    expect($result)->toHaveCount(2);
    $strings = array_map(fn (ScopeSignature $s): string => $s->toString(), $result);
    expect($strings)->toContain('locale:en');
    expect($strings)->toContain('locale:fr');
});

it('returns the cartesian product of paths across multiple given axes', function (): void {
    $registry = makeServedScopesRegistry(
        axesMap: [
            'locale' => ['__default', 'en', 'fr'],
            'market' => ['__default', 'us', 'eu'],
        ],
        defaults: ['locale' => '__default', 'market' => '__default'],
    );
    $provider = new CartesianServedScopesProvider($registry);

    $result = $provider->signatures(['locale', 'market']);

    // 2 locale paths × 2 market paths = 4
    expect($result)->toHaveCount(4);
    $strings = array_map(fn (ScopeSignature $s): string => $s->toString(), $result);
    expect($strings)->toContain('locale:en|market:us');
    expect($strings)->toContain('locale:en|market:eu');
    expect($strings)->toContain('locale:fr|market:us');
    expect($strings)->toContain('locale:fr|market:eu');
});

it('excludes each axis default path from the signatures', function (): void {
    $registry = makeServedScopesRegistry(
        axesMap: ['locale' => ['global', 'en', 'fr']],
        defaults: ['locale' => 'global'],
    );
    $provider = new CartesianServedScopesProvider($registry);

    $result = $provider->signatures(['locale']);

    $strings = array_map(fn (ScopeSignature $s): string => $s->toString(), $result);
    expect($strings)->not->toContain('locale:global');
    expect($strings)->toContain('locale:en');
    expect($strings)->toContain('locale:fr');
    expect($result)->toHaveCount(2);
});

it('produces signatures whose toString matches ScopeSignature ksorted serialization', function (): void {
    // Use axes whose names are intentionally in reverse alphabetical order to exercise ksort
    $registry = makeServedScopesRegistry(
        axesMap: [
            'zone' => ['__default', 'west'],
            'locale' => ['__default', 'en'],
        ],
        defaults: ['zone' => '__default', 'locale' => '__default'],
    );
    $provider = new CartesianServedScopesProvider($registry);

    $result = $provider->signatures(['zone', 'locale']);

    // There should be exactly 1 result: zone:west × locale:en
    expect($result)->toHaveCount(1);

    $signature = $result[0];

    // Manually build what ScopeSignature ksorted serialization produces:
    // axes ksorted => locale < zone, so: "locale:en|zone:west"
    $expected = ScopeSignature::fromArray(['zone' => 'west', 'locale' => 'en']);

    expect($signature->toString())->toBe($expected->toString());
    expect($signature->toString())->toBe('locale:en|zone:west');
});

it('caps the signature count and logs when the cartesian product exceeds the maximum', function (): void {
    // Build enough paths to exceed MAX_SIGNATURES (1024).
    // Use two axes: axis-a has 33 paths, axis-b has 32 paths => 33 × 32 = 1056 > 1024.
    $axisAPaths = array_map(fn (int $i): string => "a$i", range(1, 33));
    $axisBPaths = array_map(fn (int $i): string => "b$i", range(1, 32));

    $registry = makeServedScopesRegistry(
        axesMap: [
            'axis-a' => array_merge(['__default'], $axisAPaths),
            'axis-b' => array_merge(['__default'], $axisBPaths),
        ],
        defaults: ['axis-a' => '__default', 'axis-b' => '__default'],
    );
    $provider = new CartesianServedScopesProvider($registry);

    $warnings = [];
    set_error_handler(function (int $errno, string $errstr) use (&$warnings): bool {
        $warnings[] = $errstr;

        return true;
    }, E_USER_WARNING);

    $result = $provider->signatures(['axis-a', 'axis-b']);

    restore_error_handler();

    expect($result)->toHaveCount(CartesianServedScopesProvider::MAX_SIGNATURES);
    expect($warnings)->toHaveCount(1);
    expect($warnings[0])->toContain('CartesianServedScopesProvider');
    expect($warnings[0])->toContain((string) CartesianServedScopesProvider::MAX_SIGNATURES);
});
