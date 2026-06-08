<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pricing\BasePriceContributor;
use Markommerce\Catalog\Pricing\BatchPriceResolver;
use Markommerce\Catalog\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Catalog\Pricing\Exceptions\PriceUnavailableException;
use Markommerce\Catalog\Pricing\PriceContext;
use Markommerce\Catalog\Pricing\PriceContributorRegistry;
use Markommerce\Catalog\Pricing\PriceResolver;
use Markommerce\Catalog\Pricing\RawProductBasePriceProvider;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\ValueObjects\ConfigRow;
use Markommerce\Currency\Config\CurrencyConfig;
use Markommerce\Currency\CurrencyResolver;
use Markommerce\Money\Contracts\CurrencyRegistryInterface;
use Markommerce\Money\DefaultCurrencyRegistry;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function featureBuildPlainConfigResolver(string $baseCode = 'USD'): ConfigResolver
{
    $builder  = new ConfigRegistryBuilder();
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

function featureBuildCurrencyResolver(string $baseCode = 'USD'): CurrencyResolver
{
    return new CurrencyResolver(featureBuildPlainConfigResolver($baseCode), new DefaultCurrencyRegistry());
}

function featureBuildPriceResolver(string $baseCurrencyCode = 'USD'): PriceResolver
{
    $registry = new PriceContributorRegistry();
    $registry->register(new BasePriceContributor(new RawProductBasePriceProvider()));
    return new PriceResolver(
        new BatchPriceResolver($registry, featureBuildCurrencyResolver($baseCurrencyCode)),
    );
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('resolves a product price into money using the base currency', function (): void {
    $product             = new Product();
    $product->sku         = 'SKU-001';
    $product->priceAmount = '29.99';

    $context  = PriceContext::forProduct($product);
    $resolver = featureBuildPriceResolver();

    $money = $resolver->resolve($context);

    expect($money->amount())->toBe('29.99')
        ->and($money->currency()->code)->toBe('USD');
});

it('throws PriceUnavailableException when the product has no price amount', function (): void {
    $product             = new Product();
    $product->sku         = 'SKU-004';
    $product->priceAmount = null;

    $context  = PriceContext::forProduct($product);
    $resolver = featureBuildPriceResolver();

    expect(fn () => $resolver->resolve($context))
        ->toThrow(PriceUnavailableException::class);
});

it('binds the base resolver to the price resolver interface', function (): void {
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ConfigRepositoryInterface::class, new ConfigRepository([]));

    $catalogModule = require dirname(__DIR__, 3) . '/module.php';

    foreach ($catalogModule['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    foreach ($catalogModule['singletons'] ?? [] as $singleton) {
        $container->singleton($singleton);
    }

    $container->bind(ConfigResolver::class, fn () => featureBuildPlainConfigResolver());
    $container->bind(ConfigResolverInterface::class, fn () => featureBuildPlainConfigResolver());
    $container->bind(CurrencyRegistryInterface::class, DefaultCurrencyRegistry::class);

    $catalogModule['boot']($container->get(PriceContributorRegistry::class), $container->get(BasePriceContributor::class));

    $resolver = $container->get(PriceResolverInterface::class);

    expect($resolver)->toBeInstanceOf(PriceResolver::class);
});

it('produces the same money for a product whether resolved singly or in a batch', function (): void {
    $product             = new Product();
    $product->priceAmount = '49.99';

    $registry = new PriceContributorRegistry();
    $registry->register(new BasePriceContributor(new RawProductBasePriceProvider()));
    $currencyResolver = featureBuildCurrencyResolver();
    $batchResolver    = new BatchPriceResolver($registry, $currencyResolver);
    $priceResolver    = new PriceResolver($batchResolver);

    $singleMoney  = $priceResolver->resolve(PriceContext::forProduct($product));
    $batchResults = $batchResolver->resolve([0 => $product]);

    expect($singleMoney->amount())->toBe($batchResults[0]->amount());
    expect($singleMoney->currency()->code)->toBe($batchResults[0]->currency()->code);
});
