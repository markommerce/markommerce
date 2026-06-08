<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pricing\RawProductBasePriceProvider;

it('returns the raw price amount for each product preserving caller keys', function (): void {
    $productA = new Product();
    $productA->sku = 'SKU-A';
    $productA->priceAmount = '19.99';

    $productB = new Product();
    $productB->sku = 'SKU-B';
    $productB->priceAmount = '39.99';

    $provider = new RawProductBasePriceProvider();

    $result = $provider->amountsFor(['first' => $productA, 'second' => $productB]);

    expect($result)->toBe(['first' => '19.99', 'second' => '39.99']);
});

it('returns null for a product with no price amount', function (): void {
    $product = new Product();
    $product->sku = 'SKU-NULL';
    $product->priceAmount = null;

    $provider = new RawProductBasePriceProvider();

    $result = $provider->amountsFor([0 => $product]);

    expect($result)->toBe([0 => null]);
});

it('does not query scope storage to resolve a base amount', function (): void {
    $product = new Product();
    $product->sku = 'SKU-NOSCOPE';
    $product->priceAmount = '9.99';

    // RawProductBasePriceProvider has no constructor dependencies —
    // it cannot possibly call into scope storage
    $reflection = new ReflectionClass(RawProductBasePriceProvider::class);
    $constructor = $reflection->getConstructor();

    expect($constructor)->toBeNull('RawProductBasePriceProvider must have no constructor dependencies');

    $provider = new RawProductBasePriceProvider();
    $result = $provider->amountsFor(['k' => $product]);

    expect($result)->toBe(['k' => '9.99']);
});
