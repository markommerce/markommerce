<?php

declare(strict_types=1);

use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Config\Cache\CachingConfigResolver;
use Markommerce\Config\Cache\RequestConfigCache;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Contracts\ConfigCacheInterface;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Resolution\OverrideMatcher;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\Tests\Fakes\FakeScopeRegistry;
use Markommerce\Config\ValueObjects\ConfigRow;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

// ---- Fixture config classes ----

class CachingUnscopedConfig
{
    #[\Markommerce\Config\Attributes\Config(key: 'caching/test.value')]
    public string $value = 'default';
}

class CachingScopedConfig
{
    #[\Markommerce\Config\Attributes\Config(key: 'caching/scoped.value')]
    #[\Markommerce\Scope\Attributes\Scoped(axes: ['store'])]
    public string $value = 'scoped-default';
}

class CachingMultiAxisConfig
{
    #[\Markommerce\Config\Attributes\Config(key: 'caching/multi.value')]
    #[\Markommerce\Scope\Attributes\Scoped(axes: ['store', 'locale'])]
    public string $value = 'multi-default';
}

// ---- Helpers ----

/**
 * @param array<string, list<string>> $axes
 */
function makeCachingScopeRegistry(array $axes): ScopeRegistryInterface
{
    return new class ($axes) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        /** @param array<string, list<string>> $axes */
        public function __construct(array $axes)
        {
            $this->builtAxes = [];

            foreach ($axes as $name => $paths) {
                $default = $paths[0] ?? 'default';
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

function makeCachingConfigResolver(
    ConfigRegistry $registry,
    InMemoryConfigStorage $storage,
    ScopeContext $context,
): ConfigResolver {
    $scopeRegistry = new FakeScopeRegistry(['store', 'channel', 'locale']);
    $enumerator = new SignatureCandidateEnumerator($scopeRegistry);
    $overrideMatcher = new OverrideMatcher($enumerator);
    $valueCaster = new ValueCaster();
    $secretCipher = new NullSecretCipher();
    $proxyLocator = new ProxyLocator();
    $preferenceRegistry = new PreferenceRegistry();

    return new ConfigResolver(
        configRegistry: $registry,
        configStorage: $storage,
        overrideMatcher: $overrideMatcher,
        valueCaster: $valueCaster,
        scopeContext: $context,
        secretCipher: $secretCipher,
        proxyLocator: $proxyLocator,
        preferenceRegistry: $preferenceRegistry,
    );
}

// ---- Tests ----

it('it projects the cache key over only the config\'s declared axes ignoring unrelated active axes', function (): void {
    $scopeRegistry = makeCachingScopeRegistry([
        'store'   => ['default', 'eu'],
        'locale'  => ['en', 'fr'],
        'channel' => ['web', 'app'],
    ]);

    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([CachingScopedConfig::class], $scopeRegistry);
    $storage = new InMemoryConfigStorage();

    // Context has store + channel + locale active, but config only declares 'store'
    $context = new ScopeContext($scopeRegistry);
    $context->in('store', 'eu')->in('channel', 'app');

    $storage->compareAndSave('caching/scoped.value', new ConfigRow(
        key: 'caching/scoped.value',
        value: 'global-value',
        overrides: [],
        version: 0,
    ), 0);

    $innerResolver = makeCachingConfigResolver($registry, $storage, $context);
    $cache = new RequestConfigCache();
    $cachingResolver = new CachingConfigResolver($innerResolver, $cache, $registry, $context);

    // Pre-seed the cache with a key that only uses 'store' (not 'channel')
    $cache->get('caching/scoped.value|store:eu', fn () => 'cached-value');

    // Now resolving should hit the cache and NOT go to the inner resolver
    $result = $cachingResolver->resolved(CachingScopedConfig::class, 'value');

    expect($result)->toBe('cached-value');
});

it('CachingConfigResolver delegates to the wrapped ConfigResolver on cache miss and stores the result', function (): void {
    $scopeRegistry = new FakeScopeRegistry(['store']);
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([CachingUnscopedConfig::class], $scopeRegistry);
    $storage = new InMemoryConfigStorage();
    $context = new ScopeContext($scopeRegistry);

    $storage->compareAndSave('caching/test.value', new ConfigRow(
        key: 'caching/test.value',
        value: 'stored-value',
        overrides: [],
        version: 0,
    ), 0);

    $innerResolver = makeCachingConfigResolver($registry, $storage, $context);
    $cache = new RequestConfigCache();
    $cachingResolver = new CachingConfigResolver($innerResolver, $cache, $registry, $context);

    $result = $cachingResolver->resolved(CachingUnscopedConfig::class, 'value');

    expect($result)->toBe('stored-value');
});

it('CachingConfigResolver returns the cached value without invoking the wrapped resolver on cache hit', function (): void {
    $scopeRegistry = new FakeScopeRegistry(['store']);
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([CachingUnscopedConfig::class], $scopeRegistry);
    $storage = new InMemoryConfigStorage();
    $context = new ScopeContext($scopeRegistry);

    $storage->compareAndSave('caching/test.value', new ConfigRow(
        key: 'caching/test.value',
        value: 'stored-value',
        overrides: [],
        version: 0,
    ), 0);

    $innerResolver = makeCachingConfigResolver($registry, $storage, $context);
    $cache = new RequestConfigCache();
    $cachingResolver = new CachingConfigResolver($innerResolver, $cache, $registry, $context);

    // Pre-seed cache
    $cache->get('caching/test.value', fn () => 'pre-cached-value');

    $result = $cachingResolver->resolved(CachingUnscopedConfig::class, 'value');

    // Should return pre-cached, NOT the stored-value from storage
    expect($result)->toBe('pre-cached-value');
});
