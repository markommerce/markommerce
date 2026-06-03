<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Path\ProjectPaths;
use Markommerce\Config\Cache\CachingConfigResolver;
use Markommerce\Config\Cache\RequestConfigCache;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\ConfigWriter;
use Markommerce\Config\Contracts\ConfigCacheInterface;
use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\Contracts\ConfigWriterInterface;
use Markommerce\Config\Contracts\SecretCipherInterface;
use Markommerce\Config\Discovery\ConfigClassDiscovery;
use Markommerce\Config\Encryption\SodiumSecretCipher;
use Markommerce\Config\Exceptions\InvalidConfigClassException;
use Markommerce\Config\Exceptions\SecretCipherException;
use Markommerce\Config\Middleware\ConfigCacheResetMiddleware;
use Markommerce\Config\Proxy\ProxyAutoloader;
use Markommerce\Config\Proxy\ProxyGenerator;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Proxy\ProxyWriter;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Registry\ConfigRegistryBuilder;

return [
    'bindings' => [
        ConfigWriterInterface::class => ConfigWriter::class,
        SecretCipherInterface::class => static function (ContainerInterface $container): SecretCipherInterface {
            $encoded = getenv('MARKOMMERCE_CONFIG_SECRET_KEY');

            if ($encoded === false || $encoded === '') {
                throw SecretCipherException::notConfigured();
            }

            $key = base64_decode($encoded, strict: true);

            if ($key === false) {
                throw SecretCipherException::notConfigured();
            }

            return new SodiumSecretCipher($key);
        },
        ConfigCacheInterface::class => RequestConfigCache::class,
        ConfigResolver::class => static function (ContainerInterface $container): CachingConfigResolver {
            $baseResolver = new ConfigResolver(
                $container->get(ConfigRegistry::class),
                $container->get(ConfigStorageInterface::class),
                $container->get(ValueCaster::class),
                $container->get(SecretCipherInterface::class),
                $container->get(ProxyLocator::class),
                $container->get(PreferenceRegistry::class),
            );

            return new CachingConfigResolver(
                $baseResolver,
                $container->get(ConfigCacheInterface::class),
                $container->get(ConfigRegistry::class),
            );
        },
        ConfigResolverInterface::class => static fn (ContainerInterface $container): ConfigResolverInterface
            => $container->get(ConfigResolver::class),
    ],
    'singletons' => [
        ConfigRegistry::class,
        ConfigResolver::class,
        ProxyLocator::class,
        ProxyAutoloader::class,
        RequestConfigCache::class,
    ],
    'boot' => static function (ContainerInterface $container): void {
        // 1. Register PreferenceRegistry instance in the container
        $preferenceRegistry = $container->get(PreferenceRegistry::class);
        $container->instance(PreferenceRegistry::class, $preferenceRegistry);

        // 2. Discover config classes via attribute scan
        $discovery = $container->get(ConfigClassDiscovery::class);
        $configClasses = $discovery->discover();

        // 3. Build the ConfigRegistry from discovered config classes
        $builder = $container->get(ConfigRegistryBuilder::class);
        $registry = $builder->build($configClasses);
        $container->instance(ConfigRegistry::class, $registry);

        // 4. Register ProxyAutoloader so generated proxies become loadable
        $projectPaths = $container->get(ProjectPaths::class);
        $generatedDir = $projectPaths->base . '/var/generated/config';
        $autoloader = new ProxyAutoloader($generatedDir);
        $autoloader->register();
        $container->instance(ProxyAutoloader::class, $autoloader);

        // 5. Dev-mode codegen (only when markommerce.config.auto_regenerate === true)
        $configRepo = $container->get(ConfigRepositoryInterface::class);
        $autoRegenerate = $configRepo->has('markommerce.config.auto_regenerate')
            && $configRepo->getBool('markommerce.config.auto_regenerate') === true;

        if ($autoRegenerate) {
            $generator = $container->get(ProxyGenerator::class);
            $writer = $container->get(ProxyWriter::class);
            $proxyLocator = $container->get(ProxyLocator::class);

            foreach ($configClasses as $configClass) {
                $proxyClass = $proxyLocator->proxyClassFor($configClass);
                $proxyRelative = str_replace('\\', DIRECTORY_SEPARATOR, $proxyClass) . '.php';
                $proxyFile = rtrim($generatedDir, DIRECTORY_SEPARATOR)
                    . DIRECTORY_SEPARATOR . $proxyRelative;

                $sourceFile = (new ReflectionClass($configClass))->getFileName();
                $isStale = !file_exists($proxyFile)
                    || ($sourceFile !== false && filemtime($sourceFile) > filemtime($proxyFile));

                if ($isStale) {
                    $definitions = array_values(array_filter(
                        $registry->all(),
                        static fn ($def) => $def->configClass === $configClass,
                    ));

                    /** @throws InvalidConfigClassException */
                    $source = $generator->generate($configClass, $definitions);
                    $writer->write($proxyClass, $source, $generatedDir);
                }
            }
        }
    },
    'globalMiddleware' => [
        ['class' => ConfigCacheResetMiddleware::class, 'priority' => 10],
    ],
];
