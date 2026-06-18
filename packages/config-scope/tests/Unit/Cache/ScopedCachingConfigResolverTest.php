<?php

declare(strict_types=1);

use Marko\Core\Attributes\Preference;
use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Config\Attributes\Config;
use Markommerce\Config\Cache\CachingConfigResolver;
use Markommerce\Config\Cache\RequestConfigCache;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\ValueObjects\ConfigRow;
use Markommerce\ConfigScope\Cache\ScopedCachingConfigResolver;
use Markommerce\ConfigScope\Resolution\OverrideMatcher;
use Markommerce\ConfigScope\ScopedConfigResolver;
use Markommerce\ConfigScope\Storage\InMemoryScopedConfigStorage;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

// ─── Fixtures ─────────────────────────────────────────────────────────────────

class CachingScopedStringConfig
{
    #[Config(key: 'caching-scoped/test.stringValue')]
    public string $stringValue = 'default';
}

class CachingScopedIntConfig
{
    #[Config(key: 'caching-scoped/test.intValue')]
    public int $intValue = 0;
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * @param array<string, list<string>> $axesMap
 * @param array<string, string> $defaults
 */
function makeScopedCachingResolverRegistry(array $axesMap = [], array $defaults = []): ScopeRegistryInterface
{
    return new class ($axesMap, $defaults) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        /** @param array<string, list<string>> $axesMap @param array<string, string> $defaults */
        public function __construct(
            array $axesMap,
            array $defaults = [],
        ) {
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

/**
 * @param array<string, list<string>> $axesMap
 * @param list<class-string> $configClasses
 * @param array<string, mixed> $globalValues
 * @param array<string, array<string, mixed>> $scopeOverrides
 * @param array<string, list<string>> $scopedFields classname::field => axes
 * @param-out ScopeContext $outContext
 */
function makeScopedCachingResolver(
    array $axesMap = [],
    array $configClasses = [],
    array $globalValues = [],
    array $scopeOverrides = [],
    array $scopedFields = [],
    ?ScopeContext &$outContext = null,
    ?RequestConfigCache &$outCache = null,
): ScopedCachingConfigResolver {
    $configClasses = $configClasses ?: [CachingScopedStringConfig::class];
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build($configClasses);

    $storage = new InMemoryConfigStorage();
    foreach ($globalValues as $key => $value) {
        $storage->compareAndSave($key, new ConfigRow(key: $key, value: $value, version: 0), 0);
    }

    $scopeRegistry = makeScopedCachingResolverRegistry($axesMap);
    $enumerator = new SignatureCandidateEnumerator($scopeRegistry);
    $overrideMatcher = new OverrideMatcher($enumerator);
    $scopeContext = new ScopeContext($scopeRegistry);
    $outContext = $scopeContext;
    $scopedFieldRegistry = new ScopedFieldRegistry($scopeRegistry);

    foreach ($scopedFields as $classAndField => $axes) {
        [$class, $field] = explode('::', $classAndField, 2);
        /** @var class-string $class */
        $scopedFieldRegistry->register($class, $field, $axes);
    }

    $scopedStorage = new InMemoryScopedConfigStorage();
    foreach ($scopeOverrides as $configKey => $overrides) {
        foreach ($overrides as $signature => $value) {
            $scopedStorage->saveOverride($configKey, $signature, $value);
        }
    }

    $innerResolver = new ScopedConfigResolver(
        configRegistry: $registry,
        configStorage: $storage,
        valueCaster: new ValueCaster(),
        secretCipher: new NullSecretCipher(),
        proxyLocator: new ProxyLocator(),
        preferenceRegistry: new PreferenceRegistry(),
        scopedConfigStorage: $scopedStorage,
        overrideMatcher: $overrideMatcher,
        scopeContext: $scopeContext,
        scopedFieldRegistry: $scopedFieldRegistry,
    );

    $cache = new RequestConfigCache();
    $outCache = $cache;

    return new ScopedCachingConfigResolver(
        configResolver: $innerResolver,
        configCache: $cache,
        configRegistry: $registry,
        scopedFieldRegistry: $scopedFieldRegistry,
        scopeContext: $scopeContext,
    );
}

// ─── Tests ─────────────────────────────────────────────────────────────────────

it(
    'carries the #[Preference(replaces: CachingConfigResolver::class)] attribute on ScopedCachingConfigResolver',
    function (): void {
        $reflection = new ReflectionClass(ScopedCachingConfigResolver::class);
        $attributes = $reflection->getAttributes(Preference::class);

        expect($attributes)->not->toBeEmpty();

        $preference = $attributes[0]->newInstance();

        expect($preference->replaces)->toBe(CachingConfigResolver::class);
    },
);

it('extends Markommerce\Config\Cache\CachingConfigResolver', function (): void {
    $reflection = new ReflectionClass(ScopedCachingConfigResolver::class);

    expect($reflection->getParentClass()->getName())->toBe(CachingConfigResolver::class);
});

it(
    'builds a cache key with no axes suffix when the field is not registered in ScopedFieldRegistry',
    function (): void {
        $context = null;
        $cache = null;
        $resolver = makeScopedCachingResolver(
            axesMap: ['locale' => ['en', 'fr']],
            configClasses: [CachingScopedStringConfig::class],
            globalValues: ['caching-scoped/test.stringValue' => 'global-value'],
            scopedFields: [], // no axes registered for this field
            outContext: $context,
            outCache: $cache,
        );

        $context->in('locale', 'en');

        // Pre-seed the cache using the plain key (no scope suffix)
        $cache->get('caching-scoped/test.stringValue', fn () => 'cached-plain-value');

        $result = $resolver->resolved(CachingScopedStringConfig::class, 'stringValue');

        // Should use the plain key, not a scoped key
        expect($result)->toBe('cached-plain-value');
    },
);

it(
    'builds a cache key including active axes from ScopeContext when ScopedCachingConfigResolver resolved is called for a scoped field',
    function (): void {
        $context = null;
        $cache = null;
        $resolver = makeScopedCachingResolver(
            axesMap: ['locale' => ['en', 'fr']],
            configClasses: [CachingScopedStringConfig::class],
            globalValues: ['caching-scoped/test.stringValue' => 'global-value'],
            scopeOverrides: ['caching-scoped/test.stringValue' => ['locale:en' => 'en-value']],
            scopedFields: [CachingScopedStringConfig::class . '::stringValue' => ['locale']],
            outContext: $context,
            outCache: $cache,
        );

        $context->in('locale', 'en');

        // Pre-seed the cache with a locale-specific key
        $cache->get('caching-scoped/test.stringValue|locale:en', fn () => 'cached-en-value');

        $result = $resolver->resolved(CachingScopedStringConfig::class, 'stringValue');

        // Should have used the scoped cache key and returned cached value
        expect($result)->toBe('cached-en-value');
    },
);

it(
    'binds ConfigResolver::class in config-scope/module.php to a closure that returns a ScopedCachingConfigResolver wrapping a ScopedConfigResolver, so container::get(ConfigResolver::class) returns the cached scoped resolver (preserves Tier 1\'s caching wrap)',
    function (): void {
        $moduleArray = require dirname(__DIR__, 3) . '/module.php';

        expect($moduleArray)->toHaveKey('bindings')
            ->and($moduleArray['bindings'])->toHaveKey(ConfigResolver::class);

        $factory = $moduleArray['bindings'][ConfigResolver::class];

        expect($factory)->toBeInstanceOf(Closure::class);
    },
);

it(
    'caches resolution results per (configKey, axis context) pair, so changing the ScopeContext returns a freshly-resolved value',
    function (): void {
        $context = null;
        $resolver = makeScopedCachingResolver(
            axesMap: ['locale' => ['en', 'fr']],
            configClasses: [CachingScopedStringConfig::class],
            globalValues: ['caching-scoped/test.stringValue' => 'global-value'],
            scopeOverrides: [
                'caching-scoped/test.stringValue' => [
                    'locale:en' => 'en-value',
                    'locale:fr' => 'fr-value',
                ],
            ],
            scopedFields: [CachingScopedStringConfig::class . '::stringValue' => ['locale']],
            outContext: $context,
        );

        $context->in('locale', 'en');
        $enResult = $resolver->resolved(CachingScopedStringConfig::class, 'stringValue');

        $context->clear('locale');
        $context->in('locale', 'fr');
        $frResult = $resolver->resolved(CachingScopedStringConfig::class, 'stringValue');

        expect($enResult)->toBe('en-value')
            ->and($frResult)->toBe('fr-value'); // different cache key = freshly resolved
    },
);
