<?php

declare(strict_types=1);

use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Config\Attributes\Config;
use Markommerce\Config\Cache\CachingConfigResolver;
use Markommerce\Config\Cache\RequestConfigCache;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\ValueObjects\ConfigRow;

// ---- Fixture config classes ----

class CachingUnscopedConfig
{
    #[Config(key: 'caching/test.value')]
    public string $value = 'default';
}

// ---- Helpers ----

function makeCachingInnerResolver(
    ConfigRegistry $registry,
    InMemoryConfigStorage $storage,
): ConfigResolver {
    return new ConfigResolver(
        configRegistry: $registry,
        configStorage: $storage,
        valueCaster: new ValueCaster(),
        secretCipher: new NullSecretCipher(),
        proxyLocator: new ProxyLocator(),
        preferenceRegistry: new PreferenceRegistry(),
    );
}

// ---- Tests ----

it(
    'constructs CachingConfigResolver with three dependencies: configResolver, configCache, configRegistry',
    function (): void {
        $builder = new ConfigRegistryBuilder();
        $registry = $builder->build([CachingUnscopedConfig::class]);
        $storage = new InMemoryConfigStorage();
        $innerResolver = makeCachingInnerResolver($registry, $storage);
        $cache = new RequestConfigCache();

        $cachingResolver = new CachingConfigResolver($innerResolver, $cache, $registry);

        expect($cachingResolver)->toBeInstanceOf(CachingConfigResolver::class);
    },
);

it(
    'it caches resolution results keyed solely by the configKey when CachingConfigResolver resolved is called',
    function (): void {
        $builder = new ConfigRegistryBuilder();
        $registry = $builder->build([CachingUnscopedConfig::class]);
        $storage = new InMemoryConfigStorage();

        $storage->compareAndSave('caching/test.value', new ConfigRow(
            key: 'caching/test.value',
            value: 'stored-value',
            version: 0,
        ), 0);

        $innerResolver = makeCachingInnerResolver($registry, $storage);
        $cache = new RequestConfigCache();
        $cachingResolver = new CachingConfigResolver($innerResolver, $cache, $registry);

        // Pre-seed the cache with the exact key (no scope suffix)
        $cache->get('caching/test.value', fn () => 'pre-cached-value');

        // Resolution should return the cached value, not the stored value
        $result = $cachingResolver->resolved(CachingUnscopedConfig::class, 'value');

        expect($result)->toBe('pre-cached-value');
    },
);

it(
    'it declares CachingConfigResolver\'s constructor-promoted properties (configResolver, configCache, configRegistry) with protected visibility (verified via reflection)',
    function (): void {
        $reflection = new ReflectionClass(CachingConfigResolver::class);

        $expectedProtected = ['configResolver', 'configCache', 'configRegistry'];

        foreach ($expectedProtected as $propertyName) {
            $property = $reflection->getProperty($propertyName);
            expect($property->isProtected())->toBeTrue(
                "Expected property '$propertyName' to be protected",
            );
        }
    },
);

it(
    'it declares CachingConfigResolver::buildCacheKey() with protected visibility so subclasses can override it (verified via reflection)',
    function (): void {
        $reflection = new ReflectionClass(CachingConfigResolver::class);

        $method = $reflection->getMethod('buildCacheKey');

        expect($method->isProtected())->toBeTrue();
    },
);
