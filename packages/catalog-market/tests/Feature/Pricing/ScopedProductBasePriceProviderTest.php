<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceDiscovery;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Module\ModuleManifest;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Catalog\Pricing\Contracts\ProductBasePriceProviderInterface;
use Markommerce\Catalog\Pricing\PriceContext;
use Markommerce\Catalog\Pricing\RawProductBasePriceProvider;
use Markommerce\CatalogMarket\Pricing\ScopedProductBasePriceProvider;
use Markommerce\CatalogScope\Entity\ProductScopedOverrides;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\Contracts\SecretCipherInterface;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\ConfigScope\Contracts\ScopedConfigStorageInterface;
use Markommerce\ConfigScope\ScopedConfigResolver;
use Markommerce\ConfigScope\Storage\InMemoryScopedConfigStorage;
use Markommerce\Currency\Config\CurrencyConfig;
use Markommerce\Money\Contracts\CurrencyRegistryInterface;
use Markommerce\Money\DefaultCurrencyRegistry;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Storage\DefaultScopeGuard;

// ─── Helpers for tests 1, 2, 4, 6 ────────────────────────────────────────────

/**
 * Build a container wired with the scope module (singletons + bindings) and a
 * ConfigRepository that includes the market axis.
 */
function buildMarketPricingContainer(): Container
{
    $config = new ConfigRepository([
        'scope' => [
            'axes' => [
                'market' => [
                    'default' => 'default',
                    'scopes'  => ['default' => [], 'us' => []],
                ],
            ],
        ],
    ]);

    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);
    $container->instance(ContainerInterface::class, $container);

    $scopeModule = require dirname(__DIR__, 4) . '/scope/module.php';

    foreach ($scopeModule['singletons'] as $singleton) {
        $container->singleton($singleton);
    }

    foreach ($scopeModule['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    return $container;
}

/**
 * Boot the scope and catalog-market modules in dependency order.
 */
function bootMarketPricingModules(Container $container): void
{
    $scopeModule = require dirname(__DIR__, 4) . '/scope/module.php';
    $container->call($scopeModule['boot']);

    $catalogMarketModule = require dirname(__DIR__, 3) . '/module.php';
    $container->call($catalogMarketModule['boot']);
}

// ─── Helpers for test 3 (currency override) ───────────────────────────────────

/**
 * Build a container wired with scope + config infrastructure + currency + catalog +
 * catalog-market. Uses InMemoryScopedConfigStorage so tests can seed per-market
 * currency overrides without a database.
 */
function buildCurrencyOverridePricingContainer(InMemoryScopedConfigStorage $scopedStorage): Container
{
    $config = new ConfigRepository([
        'scope' => [
            'axes' => [
                'market' => [
                    'default' => 'default',
                    'scopes'  => ['default' => [], 'us' => []],
                ],
            ],
        ],
    ]);

    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);
    $container->instance(ContainerInterface::class, $container);

    // Scope module singletons + bindings
    $scopeModule = require dirname(__DIR__, 4) . '/scope/module.php';

    foreach ($scopeModule['singletons'] as $singleton) {
        $container->singleton($singleton);
    }

    foreach ($scopeModule['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    // Config infrastructure (manually, without the full config module boot)
    $configRegistry = (new ConfigRegistryBuilder())->build([CurrencyConfig::class]);
    $container->instance(ConfigRegistry::class, $configRegistry);
    $container->instance(ConfigStorageInterface::class, new InMemoryConfigStorage());
    $container->instance(ScopedConfigStorageInterface::class, $scopedStorage);
    $container->instance(ValueCaster::class, new ValueCaster());
    $container->instance(SecretCipherInterface::class, new NullSecretCipher());
    $container->instance(ProxyLocator::class, new ProxyLocator());
    $container->instance(PreferenceRegistry::class, new PreferenceRegistry());

    // ScopedConfigResolver handles per-market config overrides (e.g. currency/base)
    $container->bind(ConfigResolver::class, ScopedConfigResolver::class);
    $container->bind(ConfigResolverInterface::class, ScopedConfigResolver::class);

    // Currency
    $container->instance(CurrencyRegistryInterface::class, new DefaultCurrencyRegistry());

    // Catalog pricing — load from module.php so the wiring adapts to future refactors
    $catalogModule = require dirname(__DIR__, 4) . '/catalog/module.php';

    foreach ($catalogModule['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    foreach ($catalogModule['singletons'] ?? [] as $singleton) {
        $container->singleton($singleton);
    }

    // Override the raw provider with the scoped one (mirrors catalog-market preference)
    $container->bind(ProductBasePriceProviderInterface::class, ScopedProductBasePriceProvider::class);

    return $container;
}

/**
 * Boot scope, catalog (if it has a boot), catalog-market, and currency-market in order.
 */
function bootCurrencyOverridePricingModules(Container $container): void
{
    $scopeModule = require dirname(__DIR__, 4) . '/scope/module.php';
    $container->call($scopeModule['boot']);

    $catalogModule = require dirname(__DIR__, 4) . '/catalog/module.php';

    if (isset($catalogModule['boot'])) {
        $container->call($catalogModule['boot']);
    }

    $catalogMarketModule = require dirname(__DIR__, 3) . '/module.php';
    $container->call($catalogMarketModule['boot']);

    $currencyMarketModule = require dirname(__DIR__, 4) . '/currency-market/module.php';
    $container->call($currencyMarketModule['boot']);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it(
    'resolves the per market price amount from the product scoped overrides companion under the ambient market',
    function (): void {
        DefaultScopeGuard::reset();

        $container = buildMarketPricingContainer();
        bootMarketPricingModules($container);

        $scopeContext = $container->get(ScopeContext::class);
        $scopeContext->clearAll();
        $scopeContext->in('market', 'us');

        $product = new Product();
        $product->priceAmount = '99.99';

        $overrides = new ProductScopedOverrides();
        $overrides->setOverride('market:us', 'priceAmount', '79.99');
        $product->attachCompanion($overrides);

        $provider = new ScopedProductBasePriceProvider($container->get(ScopeResolver::class));
        $amounts  = $provider->amountsFor([$product]);

        expect($amounts[0])->toBe('79.99');
    },
);

it('falls back to the raw price amount when the product has no market override', function (): void {
    DefaultScopeGuard::reset();

    $container = buildMarketPricingContainer();
    bootMarketPricingModules($container);

    $scopeContext = $container->get(ScopeContext::class);
    $scopeContext->clearAll();
    $scopeContext->in('market', 'us');

    $product = new Product();
    $product->priceAmount = '99.99';
    // No ProductScopedOverrides companion attached — no market override

    $provider = new ScopedProductBasePriceProvider($container->get(ScopeResolver::class));
    $amounts  = $provider->amountsFor([$product]);

    expect($amounts[0])->toBe('99.99');
});

it('uses the per market currency override when one is configured', function (): void {
    DefaultScopeGuard::reset();

    // Pre-seed EUR as the US market currency override
    $scopedStorage = new InMemoryScopedConfigStorage();
    $scopedStorage->saveOverride('currency/base', 'market:us', 'EUR');

    $container = buildCurrencyOverridePricingContainer($scopedStorage);
    bootCurrencyOverridePricingModules($container);

    $scopeContext = $container->get(ScopeContext::class);
    $scopeContext->clearAll();
    $scopeContext->in('market', 'us');

    $product = new Product();
    $product->priceAmount = '29.99';

    $resolver = $container->get(PriceResolverInterface::class);
    $money    = $resolver->resolve(PriceContext::forProduct($product));

    expect($money->currency()->code)->toBe('EUR');
});

it('leaves the ambient market scope unchanged after resolving', function (): void {
    DefaultScopeGuard::reset();

    $container = buildMarketPricingContainer();
    bootMarketPricingModules($container);

    $scopeContext = $container->get(ScopeContext::class);
    $scopeContext->clearAll();
    $scopeContext->in('market', 'us');

    $product = new Product();
    $product->priceAmount = '19.99';

    $provider = new ScopedProductBasePriceProvider($container->get(ScopeResolver::class));
    $provider->amountsFor([$product]);

    // The provider reads the ambient context but must never mutate it
    expect($scopeContext->get('market'))->toBe('us');
});

it('overrides the raw base price provider via preference', function (): void {
    $discovery = new PreferenceDiscovery();
    $manifest  = new ModuleManifest(
        name:    'markommerce/catalog-market',
        version: '1.0.0',
        path:    dirname(__DIR__, 3),
    );

    $records = $discovery->discoverInModule($manifest);

    $match = array_find(
        $records,
        fn (object $r): bool => $r->replaces === RawProductBasePriceProvider::class,
    );

    expect($match)->not->toBeNull();
    expect($match->replacement)->toBe(ScopedProductBasePriceProvider::class);
});

// ─── Folded from PriceAmountScopeResolutionTest (lower-layer ScopeResolver assertions) ──

it('resolves a per market product price override when one is set', function (): void {
    DefaultScopeGuard::reset();

    $container = buildMarketPricingContainer();
    bootMarketPricingModules($container);

    $scopeResolver = $container->get(ScopeResolver::class);
    $scopeContext = $container->get(ScopeContext::class);

    $product = new Product();
    $product->priceAmount = '99.9900';

    $overrides = new ProductScopedOverrides();
    $overrides->setOverride('market:us', 'priceAmount', '79.9900');
    $product->attachCompanion($overrides);

    $scopeContext->clearAll();
    $scopeContext->in('market', 'us');

    $result = $scopeResolver->resolved($product, 'priceAmount');

    expect($result)->toBe('79.9900');
});

it('falls back to the global product price when no market override exists', function (): void {
    DefaultScopeGuard::reset();

    $container = buildMarketPricingContainer();
    bootMarketPricingModules($container);

    $scopeResolver = $container->get(ScopeResolver::class);
    $scopeContext = $container->get(ScopeContext::class);

    $product = new Product();
    $product->priceAmount = '99.9900';

    $overrides = new ProductScopedOverrides();
    $overrides->setOverride('market:us', 'priceAmount', '79.9900');
    $product->attachCompanion($overrides);

    $scopeContext->clearAll();
    $scopeContext->in('market', 'default');

    $result = $scopeResolver->resolved($product, 'priceAmount');

    expect($result)->toBe('99.9900');
});

it('resolves base amounts for a batch of products preserving keys under the ambient market', function (): void {
    DefaultScopeGuard::reset();

    $container = buildMarketPricingContainer();
    bootMarketPricingModules($container);

    $scopeContext = $container->get(ScopeContext::class);
    $scopeContext->clearAll();
    $scopeContext->in('market', 'us');

    $p1 = new Product();
    $p1->priceAmount = '10.00';

    $p2 = new Product();
    $p2->priceAmount = '20.00';

    $overrides2 = new ProductScopedOverrides();
    $overrides2->setOverride('market:us', 'priceAmount', '15.00');
    $p2->attachCompanion($overrides2);

    // Non-sequential and string keys must be preserved
    $products = [5 => $p1, 'x' => $p2];

    $provider = new ScopedProductBasePriceProvider($container->get(ScopeResolver::class));
    $amounts  = $provider->amountsFor($products);

    expect(array_keys($amounts))->toBe([5, 'x']);
    expect($amounts[5])->toBe('10.00');
    expect($amounts['x'])->toBe('15.00');
});
