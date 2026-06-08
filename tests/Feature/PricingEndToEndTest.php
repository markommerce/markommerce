<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Module\DependencyResolver;
use Marko\Core\Module\ModuleManifest;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pricing\BasePriceContributor;
use Markommerce\Catalog\Pricing\BatchPriceResolver;
use Markommerce\Catalog\Pricing\Contracts\ProductBasePriceProviderInterface;
use Markommerce\Catalog\Pricing\PriceContributorRegistry;
use Markommerce\Catalog\Pricing\RawProductBasePriceProvider;
use Markommerce\CatalogMarket\Pricing\ScopedProductBasePriceProvider;
use Markommerce\CatalogScope\Entity\ProductScopedOverrides;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\ValueObjects\ConfigRow;
use Markommerce\ConfigScope\Contracts\ScopedConfigStorageInterface;
use Markommerce\ConfigScope\Resolution\OverrideMatcher;
use Markommerce\ConfigScope\ScopedConfigResolver;
use Markommerce\ConfigScope\Storage\InMemoryScopedConfigStorage;
use Markommerce\Currency\Config\CurrencyConfig;
use Markommerce\Currency\CurrencyResolver;
use Markommerce\Money\DefaultCurrencyRegistry;
use Markommerce\MoneyIntl\MoneyFormatter;
use Markommerce\Catalog\Pricing\PriceContext;
use Markommerce\Catalog\Pricing\PriceResolver;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignature;
use Markommerce\Scope\Storage\DefaultScopeGuard;

// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter
use Markommerce\Tax\Config\TaxConfig;
use Markommerce\Tax\TaxMode;
use Markommerce\Tax\TaxModeResolver;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Build a Container wired for Tier-1 (single-market shop): scope module only,
 * global (non-market) config. No market axis — no scope axes at all.
 */
function e2ePricingBuildTier1Container(): Container
{
    $config = new ConfigRepository([
        'scope' => [
            'axes' => [],
        ],
    ]);

    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);
    $container->instance(ContainerInterface::class, $container);

    $scopeModule = require dirname(__DIR__, 1) . '/../packages/scope/module.php';

    foreach ($scopeModule['singletons'] as $singleton) {
        $container->singleton($singleton);
    }

    foreach ($scopeModule['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    $container->singleton(ScopedConfigStorageInterface::class, InMemoryScopedConfigStorage::class);

    return $container;
}

/**
 * Build a Container wired for Tier-3 (international shop): scope module with
 * a market axis containing 'default' and 'us' scopes.
 */
function e2ePricingBuildTier3Container(): Container
{
    $config = new ConfigRepository([
        'scope' => [
            'axes' => [
                'market' => [
                    'default' => 'default',
                    'scopes'  => [
                        'default' => [],
                        'us'      => [],
                    ],
                ],
            ],
        ],
    ]);

    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);
    $container->instance(ContainerInterface::class, $container);

    $scopeModule = require dirname(__DIR__, 1) . '/../packages/scope/module.php';

    foreach ($scopeModule['singletons'] as $singleton) {
        $container->singleton($singleton);
    }

    foreach ($scopeModule['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    $container->singleton(ScopedConfigStorageInterface::class, InMemoryScopedConfigStorage::class);

    return $container;
}

/**
 * ModuleManifest for the scope module with boot (registers DefaultScopeGuard for defined axes).
 */
function e2ePricingScopeManifest(): ModuleManifest
{
    $module = require dirname(__DIR__, 1) . '/../packages/scope/module.php';

    return new ModuleManifest(
        name: 'markommerce/scope',
        version: '1.0.0',
        boot: $module['boot'],
    );
}

/**
 * ModuleManifest for the market module.
 */
function e2ePricingMarketManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/market',
        version: '1.0.0',
        require: ['markommerce/scope' => '*'],
    );
}

/**
 * ModuleManifest for the catalog module (no boot).
 */
function e2ePricingCatalogManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/catalog',
        version: '1.0.0',
    );
}

/**
 * ModuleManifest for the catalog-scope module (structural, no boot).
 */
function e2ePricingCatalogScopeManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/catalog-scope',
        version: '1.0.0',
        require: [
            'markommerce/catalog' => '*',
            'markommerce/scope'   => '*',
        ],
    );
}

/**
 * ModuleManifest for catalog-market (registers Product.priceAmount on the market axis).
 */
function e2ePricingCatalogMarketManifest(): ModuleManifest
{
    $module = require dirname(__DIR__, 1) . '/../packages/catalog-market/module.php';

    return new ModuleManifest(
        name: 'markommerce/catalog-market',
        version: '1.0.0',
        require: $module['require'],
        boot: $module['boot'],
    );
}

/**
 * ModuleManifest for currency-market (registers CurrencyConfig.base on the market axis).
 */
function e2ePricingCurrencyMarketManifest(): ModuleManifest
{
    $module = require dirname(__DIR__, 1) . '/../packages/currency-market/module.php';

    return new ModuleManifest(
        name: 'markommerce/currency-market',
        version: '1.0.0',
        require: $module['require'],
        boot: $module['boot'],
    );
}

/**
 * ModuleManifest for tax-market (registers TaxConfig.pricesIncludeTax on the market axis).
 */
function e2ePricingTaxMarketManifest(): ModuleManifest
{
    $module = require dirname(__DIR__, 1) . '/../packages/tax-market/module.php';

    return new ModuleManifest(
        name: 'markommerce/tax-market',
        version: '1.0.0',
        require: $module['require'],
        boot: $module['boot'],
    );
}

/**
 * Run boot closures for an ordered list of manifests against the container.
 *
 * @param list<ModuleManifest> $ordered
 */
function e2ePricingRunBootLoop(array $ordered, ContainerInterface $container): void
{
    foreach ($ordered as $module) {
        if ($module->boot !== null) {
            $container->call($module->boot);
        }
    }
}

/**
 * Boot all Tier-1 modules (scope only, no market axis).
 */
function e2ePricingBootTier1(Container $container): void
{
    $resolver = new DependencyResolver();

    /** @var list<ModuleManifest> $ordered */
    $ordered = $resolver->resolve([
        e2ePricingScopeManifest(),
        e2ePricingCatalogManifest(),
        e2ePricingCatalogScopeManifest(),
    ]);

    e2ePricingRunBootLoop($ordered, $container);
}

/**
 * Boot all Tier-3 modules (scope + market + catalog-market + currency-market + tax-market).
 */
function e2ePricingBootTier3(Container $container): void
{
    $resolver = new DependencyResolver();

    /** @var list<ModuleManifest> $ordered */
    $ordered = $resolver->resolve([
        e2ePricingScopeManifest(),
        e2ePricingMarketManifest(),
        e2ePricingCatalogManifest(),
        e2ePricingCatalogScopeManifest(),
        e2ePricingCatalogMarketManifest(),
        e2ePricingCurrencyMarketManifest(),
        e2ePricingTaxMarketManifest(),
    ]);

    e2ePricingRunBootLoop($ordered, $container);
}

/**
 * Build a plain (non-scoped) ConfigResolver backed by InMemoryConfigStorage.
 * Includes both CurrencyConfig and TaxConfig in the registry.
 * Optionally override the base currency code.
 */
function e2ePricingBuildPlainConfigResolver(string $baseCode = 'EUR'): ConfigResolver
{
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([CurrencyConfig::class, TaxConfig::class]);

    $storage = new InMemoryConfigStorage();

    if ($baseCode !== 'USD') {
        $storage->compareAndSave('currency/base', new ConfigRow(
            key: 'currency/base',
            value: $baseCode,
            version: 0,
        ), 0);
    }

    return new ConfigResolver(
        configRegistry: $registry,
        configStorage: $storage,
        valueCaster: new ValueCaster(),
        secretCipher: new NullSecretCipher(),
        proxyLocator: new ProxyLocator(),
        preferenceRegistry: new PreferenceRegistry(),
    );
}

/**
 * Build a ScopedConfigResolver backed by InMemoryScopedConfigStorage.
 *
 * @param array<string, array<string, mixed>> $scopedOverrides configKey => (signature => value)
 */
function e2ePricingBuildScopedConfigResolver(
    ContainerInterface $container,
    string $globalBaseCode = 'EUR',
    bool $globalPricesIncludeTax = false,
    array $scopedOverrides = [],
): ScopedConfigResolver {
    $builder = new ConfigRegistryBuilder();
    $configRegistry = $builder->build([CurrencyConfig::class, TaxConfig::class]);

    $configStorage = new InMemoryConfigStorage();

    if ($globalBaseCode !== 'USD') {
        $configStorage->compareAndSave('currency/base', new ConfigRow(
            key: 'currency/base',
            value: $globalBaseCode,
            version: 0,
        ), 0);
    }

    if ($globalPricesIncludeTax) {
        $configStorage->compareAndSave('tax/prices_include_tax', new ConfigRow(
            key: 'tax/prices_include_tax',
            value: true,
            version: 0,
        ), 0);
    }

    $scopedStorage = new InMemoryScopedConfigStorage();

    foreach ($scopedOverrides as $configKey => $signatureValues) {
        foreach ($signatureValues as $signature => $value) {
            $scopedStorage->saveOverride($configKey, $signature, $value);
        }
    }

    return new ScopedConfigResolver(
        configRegistry: $configRegistry,
        configStorage: $configStorage,
        valueCaster: new ValueCaster(),
        secretCipher: new NullSecretCipher(),
        proxyLocator: new ProxyLocator(),
        preferenceRegistry: new PreferenceRegistry(),
        scopedConfigStorage: $scopedStorage,
        overrideMatcher: $container->get(OverrideMatcher::class),
        scopeContext: $container->get(ScopeContext::class),
        scopedFieldRegistry: $container->get(ScopedFieldRegistry::class),
    );
}

/**
 * Build a PriceResolver wired with a specific base-price provider and currency resolver.
 * Mirrors a single-contributor pipeline (base price only) for end-to-end tests.
 */
function e2ePricingBuildPriceResolver(
    ProductBasePriceProviderInterface $provider,
    CurrencyResolver $currencyResolver,
): PriceResolver {
    $registry = new PriceContributorRegistry();
    $registry->register(new BasePriceContributor($provider));
    return new PriceResolver(new BatchPriceResolver($registry, $currencyResolver));
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('resolves and formats a product price for a single market shop using global currency', function (): void {
    DefaultScopeGuard::reset();

    $container = e2ePricingBuildTier1Container();
    e2ePricingBootTier1($container);

    $product = new Product();
    $product->sku = 'SKU-GLOBAL-001';
    $product->priceAmount = '49.99';

    $configResolver = e2ePricingBuildPlainConfigResolver('EUR');
    $currencyRegistry = new DefaultCurrencyRegistry();
    $currencyResolver = new CurrencyResolver($configResolver, $currencyRegistry);

    $priceResolver = e2ePricingBuildPriceResolver(new RawProductBasePriceProvider(), $currencyResolver);

    $context = PriceContext::forProduct($product);
    $money = $priceResolver->resolve($context);

    expect($money->amount())->toBe('49.99')
        ->and($money->currency()->code)->toBe('EUR');
});

it('formats the price for the active locale in a single market shop', function (): void {
    DefaultScopeGuard::reset();

    $container = e2ePricingBuildTier1Container();
    e2ePricingBootTier1($container);

    $product = new Product();
    $product->sku = 'SKU-GLOBAL-002';
    $product->priceAmount = '49.99';

    $configResolver = e2ePricingBuildPlainConfigResolver('EUR');
    $currencyRegistry = new DefaultCurrencyRegistry();
    $currencyResolver = new CurrencyResolver($configResolver, $currencyRegistry);

    $scopeContext = $container->get(ScopeContext::class);
    $scopeContext->clearAll();

    $priceResolver = e2ePricingBuildPriceResolver(new RawProductBasePriceProvider(), $currencyResolver);

    $context = PriceContext::forProduct($product);
    $money = $priceResolver->resolve($context);

    $formatter = new MoneyFormatter($scopeContext);
    $formatted = $formatter->formatFor($money, 'de_DE');

    // Assert currency symbol and numeric value digits are present
    expect($formatted)->toContain('€')
        ->and($formatted)->toContain('49')
        ->and($formatted)->toContain('99');
});

it('resolves the global tax mode for a single market shop', function (): void {
    DefaultScopeGuard::reset();

    $container = e2ePricingBuildTier1Container();
    e2ePricingBootTier1($container);

    // Global default: pricesIncludeTax = false => TaxMode::Exclusive
    $configResolver = e2ePricingBuildPlainConfigResolver('EUR');
    $taxModeResolver = new TaxModeResolver($configResolver);

    $mode = $taxModeResolver->mode();

    expect($mode)->toBe(TaxMode::Exclusive);
});

it('resolves a per market price and currency for an international shop', function (): void {
    DefaultScopeGuard::reset();

    $container = e2ePricingBuildTier3Container();
    e2ePricingBootTier3($container);

    $scopeContext = $container->get(ScopeContext::class);
    $scopeContext->clearAll();

    // Product with a US market price override
    $product = new Product();
    $product->sku = 'SKU-INTL-001';
    $product->priceAmount = '49.99'; // global price in EUR

    $overrides = new ProductScopedOverrides();
    $overrides->setOverride('market:us', 'priceAmount', '39.99');
    $product->attachCompanion($overrides);

    // US market uses USD currency
    $usMarketSignature = new ScopeSignature(['market' => 'us']);
    $scopedConfigResolver = e2ePricingBuildScopedConfigResolver(
        container: $container,
        globalBaseCode: 'EUR',
        scopedOverrides: [
            'currency/base' => [$usMarketSignature->toString() => 'USD'],
        ],
    );

    $currencyRegistry = new DefaultCurrencyRegistry();
    $currencyResolver = new CurrencyResolver($scopedConfigResolver, $currencyRegistry);

    // Activate the US market scope before resolving
    $scopeContext->in('market', 'us');

    $priceResolver = e2ePricingBuildPriceResolver(
        new ScopedProductBasePriceProvider($container->get(ScopeResolver::class)),
        $currencyResolver,
    );

    $context = PriceContext::forProduct($product);
    $money = $priceResolver->resolve($context);

    expect($money->amount())->toBe('39.99')
        ->and($money->currency()->code)->toBe('USD');
});

it('resolves a per market tax mode independently of the price for an international shop', function (): void {
    DefaultScopeGuard::reset();

    $container = e2ePricingBuildTier3Container();
    e2ePricingBootTier3($container);

    $scopeContext = $container->get(ScopeContext::class);
    $scopeContext->clearAll();

    // US market: prices include tax (Inclusive)
    $usMarketSignature = new ScopeSignature(['market' => 'us']);
    $scopedConfigResolver = e2ePricingBuildScopedConfigResolver(
        container: $container,
        globalBaseCode: 'EUR',
        globalPricesIncludeTax: false,
        scopedOverrides: [
            'tax/prices_include_tax' => [$usMarketSignature->toString() => true],
        ],
    );

    // Activate the US market scope
    $scopeContext->in('market', 'us');

    $taxModeResolver = new TaxModeResolver($scopedConfigResolver);
    $mode = $taxModeResolver->mode();

    expect($mode)->toBe(TaxMode::Inclusive);
});

it('falls back to global price currency and tax mode outside any market scope', function (): void {
    DefaultScopeGuard::reset();

    $container = e2ePricingBuildTier3Container();
    e2ePricingBootTier3($container);

    $scopeContext = $container->get(ScopeContext::class);
    $scopeContext->clearAll();

    // Product with US override — but we resolve without activating the US scope
    $product = new Product();
    $product->sku = 'SKU-INTL-002';
    $product->priceAmount = '49.99'; // global EUR price

    $overrides = new ProductScopedOverrides();
    $overrides->setOverride('market:us', 'priceAmount', '39.99');
    $product->attachCompanion($overrides);

    $usMarketSignature = new ScopeSignature(['market' => 'us']);
    $scopedConfigResolver = e2ePricingBuildScopedConfigResolver(
        container: $container,
        globalBaseCode: 'EUR',
        globalPricesIncludeTax: false,
        scopedOverrides: [
            'currency/base'          => [$usMarketSignature->toString() => 'USD'],
            'tax/prices_include_tax' => [$usMarketSignature->toString() => true],
        ],
    );

    $currencyRegistry = new DefaultCurrencyRegistry();
    $currencyResolver = new CurrencyResolver($scopedConfigResolver, $currencyRegistry);

    // Resolve WITHOUT a market context — ScopeContext has no market active
    $priceResolver = e2ePricingBuildPriceResolver(
        new ScopedProductBasePriceProvider($container->get(ScopeResolver::class)),
        $currencyResolver,
    );

    $context = PriceContext::forProduct($product);
    $money = $priceResolver->resolve($context);

    // Falls back to global EUR price and currency
    expect($money->amount())->toBe('49.99')
        ->and($money->currency()->code)->toBe('EUR');

    // Tax mode falls back to global Exclusive
    $taxModeResolver = new TaxModeResolver($scopedConfigResolver);
    $mode = $taxModeResolver->mode();

    expect($mode)->toBe(TaxMode::Exclusive);
});

it('does not modify the ambient scope context during resolution', function (): void {
    DefaultScopeGuard::reset();

    $container = e2ePricingBuildTier3Container();
    e2ePricingBootTier3($container);

    $scopeContext = $container->get(ScopeContext::class);
    $scopeContext->clearAll();

    // Set the ambient market to 'us' — the caller controls scope, not PriceResolver
    $scopeContext->in('market', 'us');

    $product = new Product();
    $product->sku = 'SKU-LEAK-001';
    $product->priceAmount = '9.99';

    $overrides = new ProductScopedOverrides();
    $overrides->setOverride('market:us', 'priceAmount', '7.99');
    $product->attachCompanion($overrides);

    $configResolver = e2ePricingBuildPlainConfigResolver('EUR');
    $currencyResolver = new CurrencyResolver($configResolver, new DefaultCurrencyRegistry());

    $priceResolver = e2ePricingBuildPriceResolver(
        new ScopedProductBasePriceProvider($container->get(ScopeResolver::class)),
        $currencyResolver,
    );

    $context = PriceContext::forProduct($product);
    $money = $priceResolver->resolve($context);

    // Resolution reads the ambient US market price
    expect($money->amount())->toBe('7.99');

    // PriceResolver must not mutate ScopeContext — market remains 'us'
    expect($scopeContext->get('market'))->toBe('us');
});
