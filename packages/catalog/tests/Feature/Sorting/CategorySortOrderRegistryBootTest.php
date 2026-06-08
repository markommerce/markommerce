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
use Markommerce\Money\Contracts\CurrencyRegistryInterface;
use Markommerce\Money\DefaultCurrencyRegistry;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Build a container with catalog module bindings + singletons loaded.
 */
function buildCatalogModuleContainerForSorting(): Container
{
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ConfigRepositoryInterface::class, new ConfigRepository([]));

    $container->instance(ConfigStorageInterface::class, new InMemoryConfigStorage());
    $container->instance(ValueCaster::class, new ValueCaster());
    $container->instance(SecretCipherInterface::class, new NullSecretCipher());
    $container->instance(ProxyLocator::class, new ProxyLocator());
    $container->instance(CurrencyRegistryInterface::class, new DefaultCurrencyRegistry());

    $container->bind(ConfigResolver::class, ConfigResolver::class);
    $container->bind(ConfigResolverInterface::class, ConfigResolver::class);

    $catalogModule = require dirname(__DIR__, 3) . '/module.php';

    foreach ($catalogModule['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    foreach ($catalogModule['singletons'] ?? [] as $singleton) {
        $container->singleton($singleton);
    }

    return $container;
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('registers the position order in the catalog module boot', function (): void {
    $container    = buildCatalogModuleContainerForSorting();
    $catalogModule = require dirname(__DIR__, 3) . '/module.php';

    if (isset($catalogModule['boot'])) {
        $container->call($catalogModule['boot']);
    }

    $registry = $container->get(CategorySortOrderRegistry::class);

    expect($registry->has('position'))->toBeTrue();
});

it('registers position as a non-keyset order', function (): void {
    $container    = buildCatalogModuleContainerForSorting();
    $catalogModule = require dirname(__DIR__, 3) . '/module.php';

    if (isset($catalogModule['boot'])) {
        $container->call($catalogModule['boot']);
    }

    $registry = $container->get(CategorySortOrderRegistry::class);
    $order    = $registry->get('position');

    expect($order)->not->toBeNull()
        ->and($order->supportsKeyset())->toBeFalse();
});
