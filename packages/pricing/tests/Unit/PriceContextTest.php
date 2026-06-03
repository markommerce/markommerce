<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Product;
use Markommerce\Money\Money;
use Markommerce\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Pricing\PriceContext;

it('builds a price context for a product', function (): void {
    $product = new Product();
    $product->sku = 'TEST-001';

    $context = PriceContext::forProduct($product);

    expect($context)->toBeInstanceOf(PriceContext::class);
});

it('builds a price context for a product within a market', function (): void {
    $product = new Product();
    $product->sku = 'TEST-001';

    $context = PriceContext::forProduct($product, 'us');

    expect($context)->toBeInstanceOf(PriceContext::class)
        ->and($context->market)->toBe('us');
});

it('exposes the product and market as readonly properties', function (): void {
    $product = new Product();
    $product->sku = 'TEST-001';

    $context = PriceContext::forProduct($product, 'eu');

    expect($context->product)->toBe($product)
        ->and($context->market)->toBe('eu');
});

it('defines a price resolver contract returning money', function (): void {
    $interface = PriceResolverInterface::class;

    expect(interface_exists($interface))->toBeTrue();

    $reflection = new ReflectionClass($interface);
    $method = $reflection->getMethod('resolve');

    expect($method->getReturnType()?->getName())->toBe(Money::class);
});
