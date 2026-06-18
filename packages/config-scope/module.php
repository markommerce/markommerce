<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Database\Connection\ConnectionInterface;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Contracts\ConfigCacheInterface;
use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\Contracts\SecretCipherInterface;
use Markommerce\Config\Discovery\ConfigClassDiscovery;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\ConfigScope\Cache\ScopedCachingConfigResolver;
use Markommerce\ConfigScope\Contracts\ScopedConfigStorageInterface;
use Markommerce\ConfigScope\PgSql\PgsqlScopedConfigStorage;
use Markommerce\ConfigScope\Resolution\OverrideMatcher;
use Markommerce\ConfigScope\ScopedConfigResolver;
use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;

return [
    'require' => [
        'markommerce/config' => '*',
        'markommerce/scope' => '*',
    ],
    'bindings' => [
        ScopedConfigStorageInterface::class => static function (ContainerInterface $container): PgsqlScopedConfigStorage {
            return new PgsqlScopedConfigStorage($container->get(ConnectionInterface::class));
        },
        ConfigResolver::class => static function (ContainerInterface $c): ScopedCachingConfigResolver {
            $base = new ScopedConfigResolver(
                configRegistry: $c->get(ConfigRegistry::class),
                configStorage: $c->get(ConfigStorageInterface::class),
                valueCaster: $c->get(ValueCaster::class),
                secretCipher: $c->get(SecretCipherInterface::class),
                proxyLocator: $c->get(ProxyLocator::class),
                preferenceRegistry: $c->get(PreferenceRegistry::class),
                scopedConfigStorage: $c->get(ScopedConfigStorageInterface::class),
                overrideMatcher: $c->get(OverrideMatcher::class),
                scopeContext: $c->get(ScopeContext::class),
                scopedFieldRegistry: $c->get(ScopedFieldRegistry::class),
            );

            return new ScopedCachingConfigResolver(
                configResolver: $base,
                configCache: $c->get(ConfigCacheInterface::class),
                configRegistry: $c->get(ConfigRegistry::class),
                scopedFieldRegistry: $c->get(ScopedFieldRegistry::class),
                scopeContext: $c->get(ScopeContext::class),
            );
        },
    ],
    'boot' => function (
        ConfigClassDiscovery $discovery,
        ScopedFieldRegistry $registry,
    ): void {
        foreach ($discovery->discover() as $configClass) {
            $reflection = new ReflectionClass($configClass);
            foreach ($reflection->getProperties() as $property) {
                $attrs = $property->getAttributes(Scoped::class);
                if ($attrs === []) {
                    continue;
                }
                $scoped = $attrs[0]->newInstance();
                $registry->register(
                    entityClass: $configClass,
                    property: $property->getName(),
                    axes: $scoped->axes,
                );
            }
        }
    },
];
