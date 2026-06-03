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
use Markommerce\CatalogScope\Entity\ProductScopedOverrides;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\ValueObjects\ConfigRow;
use Markommerce\ConfigScope\Contracts\ScopedConfigStorageInterface;
use Markommerce\ConfigScope\Storage\InMemoryScopedConfigStorage;
use Markommerce\Currency\Config\CurrencyConfig;
use Markommerce\Currency\CurrencyResolver;
use Markommerce\Money\DefaultCurrencyRegistry;
use Markommerce\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Pricing\Exceptions\PriceUnavailableException;
use Markommerce\Pricing\PriceContext;
use Markommerce\Pricing\PriceResolver;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignature;
use Markommerce\Scope\Storage\DefaultScopeGuard;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Build a Container wired with the scope module singletons/bindings and a market axis
 * config that includes both 'default' and 'us' scopes.
 */
function buildPricingContainer(): Container
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

    $scopeModule = require dirname(__DIR__, 3) . '/scope/module.php';

    foreach ($scopeModule['singletons'] as $singleton) {
        $container->singleton($singleton);
    }

    foreach ($scopeModule['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    // Bind InMemoryScopedConfigStorage for config-scope
    $container->singleton(ScopedConfigStorageInterface::class, InMemoryScopedConfigStorage::class);

    return $container;
}

/**
 * Build a plain ConfigResolver backed by InMemoryConfigStorage with an optional base currency code override.
 */
function buildPlainConfigResolver(string $baseCode = 'USD'): ConfigResolver
{
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([CurrencyConfig::class]);

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
 * Build a ModuleManifest for the scope module (singletons + bindings but no boot needed).
 */
function pricingScopeManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/scope',
        version: '1.0.0',
    );
}

/**
 * Build a ModuleManifest for the market module.
 */
function pricingMarketManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/market',
        version: '1.0.0',
        require: ['markommerce/scope' => '*'],
    );
}

/**
 * Build a ModuleManifest for the catalog module (no boot).
 */
function pricingCatalogManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/catalog',
        version: '1.0.0',
    );
}

/**
 * Build a ModuleManifest for the catalog-scope module (no boot — structural).
 */
function pricingCatalogScopeManifest(): ModuleManifest
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
 * Build a ModuleManifest for catalog-market (boots priceAmount on market axis).
 */
function pricingCatalogMarketManifest(): ModuleManifest
{
    $module = require dirname(__DIR__, 3) . '/catalog-market/module.php';

    return new ModuleManifest(
        name: 'markommerce/catalog-market',
        version: '1.0.0',
        require: $module['require'],
        boot: $module['boot'],
    );
}

/**
 * Boot all manifests in dependency order.
 *
 * @param list<ModuleManifest> $ordered
 */
function runPricingBootLoop(array $ordered, ContainerInterface $container): void
{
    foreach ($ordered as $module) {
        if ($module->boot !== null) {
            $container->call($module->boot);
        }
    }
}

/**
 * Resolve and boot all manifests needed for pricing tests.
 */
function bootPricing(Container $container): void
{
    $resolver = new DependencyResolver();
    $ordered = $resolver->resolve([
        pricingScopeManifest(),
        pricingMarketManifest(),
        pricingCatalogManifest(),
        pricingCatalogScopeManifest(),
        pricingCatalogMarketManifest(),
    ]);

    runPricingBootLoop($ordered, $container);
}

/**
 * Build a PriceResolver backed by a plain (non-scoped) ConfigResolver returning USD.
 */
function buildPriceResolver(
    Container $container,
    string $baseCurrencyCode = 'USD',
): PriceResolver {
    $configResolver = buildPlainConfigResolver($baseCurrencyCode);
    $currencyRegistry = new DefaultCurrencyRegistry();
    $currencyResolver = new CurrencyResolver($configResolver, $currencyRegistry);

    return new PriceResolver(
        scopeResolver: $container->get(ScopeResolver::class),
        scopeContext: $container->get(ScopeContext::class),
        currencyResolver: $currencyResolver,
    );
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('resolves a product price into money using the base currency', function (): void {
    DefaultScopeGuard::reset();

    $container = buildPricingContainer();
    bootPricing($container);

    $product = new Product();
    $product->sku = 'SKU-001';
    $product->priceAmount = '29.99';

    $context = PriceContext::forProduct($product);
    $resolver = buildPriceResolver($container);

    $money = $resolver->resolve($context);

    expect($money->amount())->toBe('29.99')
        ->and($money->currency()->code)->toBe('USD');
});

it(
    'resolves the per market price amount from the product scoped overrides companion when a market is given in the context',
    function (): void {
        DefaultScopeGuard::reset();
    
        $container = buildPricingContainer();
        bootPricing($container);
    
        $product = new Product();
        $product->sku = 'SKU-002';
        $product->priceAmount = '29.99';
    
        $overrides = new ProductScopedOverrides();
        $overrides->setOverride('market:us', 'priceAmount', '19.99');
        $product->attachCompanion($overrides);
    
        $context = PriceContext::forProduct($product, 'us');
        $resolver = buildPriceResolver($container);
    
        $money = $resolver->resolve($context);
    
        expect($money->amount())->toBe('19.99')
            ->and($money->currency()->code)->toBe('USD');
    }
);

it('uses the per market currency override when one is configured', function (): void {
    DefaultScopeGuard::reset();

    $container = buildPricingContainer();
    bootPricing($container);

    // Boot currency-market so CurrencyConfig.base is registered on the market axis
    $currencyMarketModule = require dirname(__DIR__, 3) . '/currency-market/module.php';
    $container->call($currencyMarketModule['boot']);

    // In-memory scoped storage with a EUR override for market=us
    $scopedStorage = new InMemoryScopedConfigStorage();
    $signature = new ScopeSignature(['market' => 'us']);
    $scopedStorage->saveOverride('currency/base', $signature->toString(), 'EUR');

    // Build the registry for CurrencyConfig (includes the 'currency/base' key)
    $builder = new ConfigRegistryBuilder();
    $configRegistry = $builder->build([CurrencyConfig::class]);
    $configStorage = new InMemoryConfigStorage();

    // Build a ScopedConfigResolver that honours the active ScopeContext
    $scopedConfigResolver = new Markommerce\ConfigScope\ScopedConfigResolver(
        configRegistry: $configRegistry,
        configStorage: $configStorage,
        valueCaster: new ValueCaster(),
        secretCipher: new NullSecretCipher(),
        proxyLocator: new ProxyLocator(),
        preferenceRegistry: new PreferenceRegistry(),
        scopedConfigStorage: $scopedStorage,
        overrideMatcher: $container->get(Markommerce\ConfigScope\Resolution\OverrideMatcher::class),
        scopeContext: $container->get(ScopeContext::class),
        scopedFieldRegistry: $container->get(ScopedFieldRegistry::class),
    );

    $currencyRegistry = new DefaultCurrencyRegistry();
    $currencyResolver = new CurrencyResolver($scopedConfigResolver, $currencyRegistry);

    $product = new Product();
    $product->sku = 'SKU-003';
    $product->priceAmount = '29.99';

    $overrides = new ProductScopedOverrides();
    $overrides->setOverride('market:us', 'priceAmount', '24.99');
    $product->attachCompanion($overrides);

    $context = PriceContext::forProduct($product, 'us');

    $resolver = new PriceResolver(
        scopeResolver: $container->get(ScopeResolver::class),
        scopeContext: $container->get(ScopeContext::class),
        currencyResolver: $currencyResolver,
    );

    $money = $resolver->resolve($context);

    expect($money->amount())->toBe('24.99')
        ->and($money->currency()->code)->toBe('EUR');
});

it('throws PriceUnavailableException when the product has no price amount', function (): void {
    DefaultScopeGuard::reset();

    $container = buildPricingContainer();
    bootPricing($container);

    $product = new Product();
    $product->sku = 'SKU-004';
    $product->priceAmount = null;

    $context = PriceContext::forProduct($product);
    $resolver = buildPriceResolver($container);

    expect(fn () => $resolver->resolve($context))
        ->toThrow(PriceUnavailableException::class);
});

it('restores the previous market scope on the shared ScopeContext after resolving', function (): void {
    DefaultScopeGuard::reset();

    $container = buildPricingContainer();
    bootPricing($container);

    $scopeContext = $container->get(ScopeContext::class);

    // Pre-set the market to 'default' (simulating an in-flight request context)
    $scopeContext->in('market', 'default');

    $product = new Product();
    $product->sku = 'SKU-005';
    $product->priceAmount = '9.99';

    $overrides = new ProductScopedOverrides();
    $overrides->setOverride('market:us', 'priceAmount', '7.99');
    $product->attachCompanion($overrides);

    $context = PriceContext::forProduct($product, 'us');
    $resolver = buildPriceResolver($container);

    $resolver->resolve($context);

    // After resolution the market scope must be restored to 'default'
    expect($scopeContext->get('market'))->toBe('default');
});

it('binds the base resolver to the price resolver interface', function (): void {
    DefaultScopeGuard::reset();

    $container = buildPricingContainer();
    bootPricing($container);

    // Load the pricing module bindings
    $pricingModule = require dirname(__DIR__, 2) . '/module.php';

    foreach ($pricingModule['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    // Wire up dependencies so the container can auto-resolve PriceResolver
    $container->bind(ConfigResolver::class, fn () => buildPlainConfigResolver());
    $container->bind(
        \Markommerce\Money\Contracts\CurrencyRegistryInterface::class,
        \Markommerce\Money\DefaultCurrencyRegistry::class,
    );

    $resolver = $container->get(PriceResolverInterface::class);

    expect($resolver)->toBeInstanceOf(PriceResolver::class);
});
