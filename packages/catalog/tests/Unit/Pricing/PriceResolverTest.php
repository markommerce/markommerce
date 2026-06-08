<?php

declare(strict_types=1);

use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pricing\BasePriceContributor;
use Markommerce\Catalog\Pricing\BatchPriceResolver;
use Markommerce\Catalog\Pricing\Contracts\BatchPriceResolverInterface;
use Markommerce\Catalog\Pricing\Contracts\ProductBasePriceProviderInterface;
use Markommerce\Catalog\Pricing\Exceptions\PriceUnavailableException;
use Markommerce\Catalog\Pricing\PriceContext;
use Markommerce\Catalog\Pricing\PriceContributorRegistry;
use Markommerce\Catalog\Pricing\PriceResolver;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Currency\Config\CurrencyConfig;
use Markommerce\Currency\CurrencyResolver;
use Markommerce\Money\Currency;
use Markommerce\Money\DefaultCurrencyRegistry;
use Markommerce\Money\Money;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function unitBuildCurrencyResolver(string $baseCode = 'USD'): CurrencyResolver
{
    $builder  = new ConfigRegistryBuilder();
    $registry = $builder->build([CurrencyConfig::class]);
    $storage  = new InMemoryConfigStorage();

    $configResolver = new ConfigResolver(
        configRegistry: $registry,
        configStorage: $storage,
        valueCaster: new ValueCaster(),
        secretCipher: new NullSecretCipher(),
        proxyLocator: new ProxyLocator(),
        preferenceRegistry: new PreferenceRegistry(),
    );

    return new CurrencyResolver($configResolver, new DefaultCurrencyRegistry());
}

function buildTestPriceResolver(
    ProductBasePriceProviderInterface $basePriceProvider,
    string $baseCurrencyCode = 'USD',
): PriceResolver {
    $registry = new PriceContributorRegistry();
    $registry->register(new BasePriceContributor($basePriceProvider));
    return new PriceResolver(new BatchPriceResolver($registry, unitBuildCurrencyResolver($baseCurrencyCode)));
}

// ─── Fakes ────────────────────────────────────────────────────────────────────

class RawProductBasePriceProviderFake implements ProductBasePriceProviderInterface
{
    /** @param list<?string> $amounts */
    public function __construct(private array $amounts) {}

    /** @param array<array-key, Product> $products */
    public function amountsFor(array $products): array
    {
        return array_map(fn () => array_shift($this->amounts), $products);
    }
}

class StubBatchPriceResolver implements BatchPriceResolverInterface
{
    /** @param array<array-key, Money> $result */
    public function __construct(private array $result) {}

    /** @param array<array-key, Product> $products */
    public function resolve(array $products): array
    {
        return $this->result;
    }
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('resolves a single product price by delegating to a batch of one', function (): void {
    $currency = new Currency('USD', 2, '$', 'US Dollar');
    $money    = Money::of('29.99', $currency);
    $stub     = new StubBatchPriceResolver([0 => $money]);
    $resolver = new PriceResolver($stub);

    $product             = new Product();
    $product->priceAmount = '29.99';

    $result = $resolver->resolve(PriceContext::forProduct($product));

    expect($result)->toBe($money);
});

it('throws PriceUnavailableException when the batch yields no amount for the product', function (): void {
    $stub     = new StubBatchPriceResolver([]);
    $resolver = new PriceResolver($stub);

    $product             = new Product();
    $product->priceAmount = null;

    expect(fn () => $resolver->resolve(PriceContext::forProduct($product)))
        ->toThrow(PriceUnavailableException::class);
});

it('resolves a price without any scope module installed', function (): void {
    $reflection  = new ReflectionClass(PriceResolver::class);
    $constructor = $reflection->getConstructor();

    expect($constructor)->not->toBeNull();

    $paramNames = array_map(
        fn (ReflectionParameter $p) => $p->getName(),
        $constructor->getParameters(),
    );

    expect($paramNames)->not->toContain('scopeResolver')
        ->and($paramNames)->not->toContain('scopeContext')
        ->and($paramNames)->not->toContain('basePriceProvider')
        ->and($paramNames)->not->toContain('currencyResolver');

    $provider = new RawProductBasePriceProviderFake(['14.99']);
    $resolver = buildTestPriceResolver($provider);

    $product             = new Product();
    $product->priceAmount = '14.99';

    $money = $resolver->resolve(PriceContext::forProduct($product));

    expect($money->amount())->toBe('14.99');
});
