<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pricing\Exceptions\InvalidBatchKeyException;
use Markommerce\Catalog\Pricing\PriceBatch;
use Markommerce\Money\Currency;

function makeCurrency(): Currency
{
    return new Currency(code: 'USD', scale: 2, symbol: '$', name: 'US Dollar');
}

function makeProduct(string $sku = 'TEST-001'): Product
{
    $product = new Product();
    $product->sku = $sku;

    return $product;
}

it('exposes the products it was built with keyed as given', function (): void {
    $p1 = makeProduct('SKU-1');
    $p2 = makeProduct('SKU-2');
    $products = [10 => $p1, 20 => $p2];
    $currency = makeCurrency();

    $batch = PriceBatch::of($products, $currency);

    expect($batch->products())->toBe($products);
});

it('exposes the base currency of the batch', function (): void {
    $product = makeProduct();
    $currency = makeCurrency();

    $batch = PriceBatch::of([$product], $currency);

    expect($batch->currency())->toBe($currency);
});

it('returns null for an amount that has not been set', function (): void {
    $product = makeProduct();
    $batch = PriceBatch::of([42 => $product], makeCurrency());

    expect($batch->amount(42))->toBeNull();
});

it('stores and returns an amount for a product key', function (): void {
    $product = makeProduct();
    $batch = PriceBatch::of([5 => $product], makeCurrency());

    $batch->setAmount(5, '19.99');

    expect($batch->amount(5))->toBe('19.99');
});

it('overwrites a previously set amount for a product key', function (): void {
    $product = makeProduct();
    $batch = PriceBatch::of([7 => $product], makeCurrency());

    $batch->setAmount(7, '10.00');
    $batch->setAmount(7, '25.50');

    expect($batch->amount(7))->toBe('25.50');
});

it('lists the product keys in the batch', function (): void {
    $p1 = makeProduct('SKU-A');
    $p2 = makeProduct('SKU-B');
    $batch = PriceBatch::of([10 => $p1, 20 => $p2], makeCurrency());

    expect($batch->keys())->toBe([10, 20]);
});

it('throws when setting an amount for a key not in the batch', function (): void {
    $product = makeProduct();
    $batch = PriceBatch::of([1 => $product], makeCurrency());

    expect(fn () => $batch->setAmount(999, '10.00'))
        ->toThrow(InvalidBatchKeyException::class);
});

it('preserves non sequential and string keys', function (): void {
    $p1 = makeProduct('SKU-X');
    $p2 = makeProduct('SKU-Y');
    $p3 = makeProduct('SKU-Z');
    $products = [100 => $p1, 'sku-key' => $p2, 999 => $p3];
    $batch = PriceBatch::of($products, makeCurrency());

    $batch->setAmount(100, '5.00');
    $batch->setAmount('sku-key', '15.00');
    $batch->setAmount(999, '99.99');

    expect($batch->products())->toBe($products)
        ->and($batch->keys())->toBe([100, 'sku-key', 999])
        ->and($batch->amount(100))->toBe('5.00')
        ->and($batch->amount('sku-key'))->toBe('15.00')
        ->and($batch->amount(999))->toBe('99.99');
});
