<?php

declare(strict_types=1);

use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pricing\BasePriceContributor;
use Markommerce\Catalog\Pricing\BatchPriceResolver;
use Markommerce\Catalog\Pricing\Contracts\PriceContributorInterface;
use Markommerce\Catalog\Pricing\PriceBatch;
use Markommerce\Catalog\Pricing\PriceContributorRegistry;
use Markommerce\Catalog\Pricing\RawProductBasePriceProvider;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Currency\Config\CurrencyConfig;
use Markommerce\Currency\CurrencyResolver;
use Markommerce\Money\DefaultCurrencyRegistry;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function batchBuildCurrencyResolver(string $code = 'USD'): CurrencyResolver
{
    $registry = (new ConfigRegistryBuilder())->build([CurrencyConfig::class]);
    $storage  = new InMemoryConfigStorage();
    $resolver = new ConfigResolver(
        configRegistry: $registry,
        configStorage: $storage,
        valueCaster: new ValueCaster(),
        secretCipher: new NullSecretCipher(),
        proxyLocator: new ProxyLocator(),
        preferenceRegistry: new PreferenceRegistry(),
    );
    return new CurrencyResolver($resolver, new DefaultCurrencyRegistry());
}

function batchBuildResolver(string $currencyCode = 'USD'): BatchPriceResolver
{
    $registry = new PriceContributorRegistry();
    $registry->register(new BasePriceContributor(new RawProductBasePriceProvider()));
    return new BatchPriceResolver($registry, batchBuildCurrencyResolver($currencyCode));
}

function batchMakeProduct(?string $priceAmount): Product
{
    $p = new Product();
    $p->priceAmount = $priceAmount;
    return $p;
}

// ─── Fakes ────────────────────────────────────────────────────────────────────

class OverwritingContributor implements PriceContributorInterface
{
    public function __construct(
        private int|string $key,
        private string $amount,
    ) {}

    public function contribute(PriceBatch $batch): void
    {
        $batch->setAmount($this->key, $this->amount);
    }
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('resolves money for every product with a base amount', function (): void {
    $resolver = batchBuildResolver();

    $p1            = batchMakeProduct('10.00');
    $p2            = batchMakeProduct('20.00');
    $result        = $resolver->resolve([1 => $p1, 2 => $p2]);

    expect($result)->toHaveKeys([1, 2]);
    expect($result[1]->amount())->toBe('10.00');
    expect($result[2]->amount())->toBe('20.00');
});

it('omits products that have no resolvable amount from the result', function (): void {
    $resolver = batchBuildResolver();

    $withAmount    = batchMakeProduct('5.00');
    $withoutAmount = batchMakeProduct(null);
    $result        = $resolver->resolve([1 => $withAmount, 2 => $withoutAmount]);

    expect($result)->toHaveKey(1);
    expect($result)->not->toHaveKey(2);
});

it('applies contributors in priority order to the batch', function (): void {
    $registry = new PriceContributorRegistry();
    $registry->register(new BasePriceContributor(new RawProductBasePriceProvider()), 0);
    // Higher priority contributor overwrites with a different amount
    $registry->register(new OverwritingContributor(1, '99.00'), 10);

    $batchResolver = new BatchPriceResolver($registry, batchBuildCurrencyResolver());

    $product = batchMakeProduct('5.00');
    $result  = $batchResolver->resolve([1 => $product]);

    expect($result[1]->amount())->toBe('99.00');
});

it('resolves the whole batch using a single base currency lookup', function (): void {
    $resolver = batchBuildResolver('USD');

    $products = [1 => batchMakeProduct('1.00'), 2 => batchMakeProduct('2.00'), 3 => batchMakeProduct('3.00')];
    $result   = $resolver->resolve($products);

    // All Money objects share the same Currency instance — proves base() called once
    expect($result[1]->currency())->toBe($result[2]->currency())
        ->and($result[2]->currency())->toBe($result[3]->currency());
});

it('returns money keyed by the caller supplied product keys', function (): void {
    $resolver = batchBuildResolver();

    $products = ['alpha' => batchMakeProduct('1.50'), 99 => batchMakeProduct('2.50')];
    $result   = $resolver->resolve($products);

    expect(array_keys($result))->toBe(['alpha', 99]);
    expect($result['alpha']->amount())->toBe('1.50');
    expect($result[99]->amount())->toBe('2.50');
});
