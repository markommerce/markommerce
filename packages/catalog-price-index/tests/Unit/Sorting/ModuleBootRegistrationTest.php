<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Markommerce\Catalog\Sorting\CategorySortOrderRegistry;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\Contracts\SecretCipherInterface;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Indexer\Registry\IndexerRegistry;
use Markommerce\Money\Contracts\CurrencyRegistryInterface;
use Markommerce\Money\DefaultCurrencyRegistry;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function buildPriceIndexModuleContainer(): Container
{
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ConfigRepositoryInterface::class, new ConfigRepository([]));
    $container->instance(ConfigStorageInterface::class, new InMemoryConfigStorage());
    $container->instance(ValueCaster::class, new ValueCaster());
    $container->instance(SecretCipherInterface::class, new NullSecretCipher());
    $container->instance(ProxyLocator::class, new ProxyLocator());
    $container->instance(CurrencyRegistryInterface::class, new DefaultCurrencyRegistry());
    $container->singleton(IndexerRegistry::class);

    $container->bind(ConfigResolver::class, ConfigResolver::class);
    $container->bind(ConfigResolverInterface::class, ConfigResolver::class);

    // Load catalog module first (provides the registry singleton)
    $catalogModule = require dirname(__DIR__, 4) . '/catalog/module.php';

    foreach ($catalogModule['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    foreach ($catalogModule['singletons'] ?? [] as $singleton) {
        $container->singleton($singleton);
    }

    if (isset($catalogModule['boot'])) {
        $container->call($catalogModule['boot']);
    }

    // Load catalog-price-index module
    $priceIndexModule = require dirname(__DIR__, 3) . '/module.php';

    foreach ($priceIndexModule['bindings'] ?? [] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    foreach ($priceIndexModule['singletons'] ?? [] as $singleton) {
        $container->singleton($singleton);
    }

    return $container;
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('it registers both price orders in the price-index module boot', function (): void {
    $container        = buildPriceIndexModuleContainer();
    $priceIndexModule = require dirname(__DIR__, 3) . '/module.php';

    if (isset($priceIndexModule['boot'])) {
        $container->call($priceIndexModule['boot']);
    }

    $registry = $container->get(CategorySortOrderRegistry::class);

    expect($registry->has('price_asc'))->toBeTrue()
        ->and($registry->has('price_desc'))->toBeTrue();
});
