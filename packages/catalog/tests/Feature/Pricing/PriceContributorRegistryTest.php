<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Markommerce\Catalog\Pricing\BasePriceContributor;
use Markommerce\Catalog\Pricing\PriceContributorRegistry;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\Contracts\SecretCipherInterface;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Currency\CurrencyResolver;
use Markommerce\Money\Contracts\CurrencyRegistryInterface;
use Markommerce\Money\DefaultCurrencyRegistry;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Build a container with catalog module bindings + singletons loaded, and
 * stub dependencies wired so BasePriceContributor can be resolved.
 */
function buildCatalogModuleContainer(): Container
{
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ConfigRepositoryInterface::class, new ConfigRepository([]));

    // Stub config/currency deps so BasePriceContributor's transitive deps resolve
    $container->instance(ConfigStorageInterface::class, new InMemoryConfigStorage());
    $container->instance(ValueCaster::class, new ValueCaster());
    $container->instance(SecretCipherInterface::class, new NullSecretCipher());
    $container->instance(ProxyLocator::class, new ProxyLocator());
    $container->instance(CurrencyRegistryInterface::class, new DefaultCurrencyRegistry());

    // Wire ConfigResolver so CurrencyResolver can be resolved
    $container->bind(ConfigResolver::class, ConfigResolver::class);
    $container->bind(ConfigResolverInterface::class, ConfigResolver::class);

    // Load catalog module
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

it('is the same registry instance across container resolutions', function (): void {
    $container = new Container();
    $container->singleton(PriceContributorRegistry::class);

    $a = $container->get(PriceContributorRegistry::class);
    $b = $container->get(PriceContributorRegistry::class);

    expect($a)->toBe($b);
});

it('registers the base price contributor at boot with the lowest priority', function (): void {
    $container    = buildCatalogModuleContainer();
    $catalogModule = require dirname(__DIR__, 3) . '/module.php';

    if (isset($catalogModule['boot'])) {
        $container->call($catalogModule['boot']);
    }

    $registry     = $container->get(PriceContributorRegistry::class);
    $contributors = $registry->all();

    expect($contributors)->not->toBeEmpty();
    expect($contributors[0])->toBeInstanceOf(BasePriceContributor::class);
});
