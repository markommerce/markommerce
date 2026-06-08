<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pricing\BasePriceContributor;
use Markommerce\Catalog\Pricing\Contracts\ProductBasePriceProviderInterface;
use Markommerce\Catalog\Pricing\PriceBatch;
use Markommerce\Money\Currency;

// ─── Fake ─────────────────────────────────────────────────────────────────────

class CountingBasePriceProvider implements ProductBasePriceProviderInterface
{
    public int $callCount = 0;

    /** @var array<array-key, ?string> */
    public array $amounts = [];

    /**
     * @param array<array-key, Product> $products
     * @return array<array-key, ?string>
     */
    public function amountsFor(array $products): array
    {
        $this->callCount++;

        return array_map(fn (Product $p) => $this->amounts[$p->id ?? 0] ?? null, $products);
    }
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('seeds the base amount for every product in the batch', function (): void {
    $p1 = new Product();
    $p1->id = 1;
    $p1->priceAmount = '10.00';

    $p2 = new Product();
    $p2->id = 2;
    $p2->priceAmount = '20.00';

    $provider          = new CountingBasePriceProvider();
    $provider->amounts = [1 => '10.00', 2 => '20.00'];

    $currency    = new Currency('USD', 2, '$', 'US Dollar');
    $batch       = PriceBatch::of([1 => $p1, 2 => $p2], $currency);
    $contributor = new BasePriceContributor($provider);
    $contributor->contribute($batch);

    expect($batch->amount(1))->toBe('10.00')
        ->and($batch->amount(2))->toBe('20.00');
});

it('seeds null for a product with no base amount', function (): void {
    $product     = new Product();
    $product->id = 99;

    $provider          = new CountingBasePriceProvider();
    $provider->amounts = [];  // no amount for product 99

    $currency    = new Currency('USD', 2, '$', 'US Dollar');
    $batch       = PriceBatch::of([99 => $product], $currency);
    $contributor = new BasePriceContributor($provider);
    $contributor->contribute($batch);

    expect($batch->amount(99))->toBeNull();
});

it('calls the base price provider once for the whole batch', function (): void {
    $products = [];

    for ($i = 1; $i <= 5; $i++) {
        $p     = new Product();
        $p->id = $i;
        $products[$i] = $p;
    }

    $provider          = new CountingBasePriceProvider();
    $provider->amounts = array_fill_keys(range(1, 5), '9.99');

    $currency    = new Currency('USD', 2, '$', 'US Dollar');
    $batch       = PriceBatch::of($products, $currency);
    $contributor = new BasePriceContributor($provider);
    $contributor->contribute($batch);

    expect($provider->callCount)->toBe(1);
});
