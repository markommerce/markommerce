<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Unit\Service;

use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Service\ProductPriceServiceInterface;
use Markommerce\Catalog\Tests\Support\FakeMoneyFactory;
use Markommerce\Money\MoneyInterface;
use ReflectionClass;
use ReflectionNamedType;

it('declares getBasePrice on ProductPriceServiceInterface returning MoneyInterface', function (): void {
    $reflection = new ReflectionClass(ProductPriceServiceInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('getBasePrice'))->toBeTrue();

    $method = $reflection->getMethod('getBasePrice');
    expect($method->getParameters())->toHaveCount(1);

    $param = $method->getParameters()[0];
    $paramType = $param->getType();
    assert($paramType instanceof ReflectionNamedType);
    expect($paramType->getName())->toBe(Product::class);

    $returnType = $method->getReturnType();
    assert($returnType instanceof ReflectionNamedType);
    expect($returnType->getName())->toBe(MoneyInterface::class);
});

it('delegates Money construction to MoneyFactoryInterface create with the product basePriceAmount', function (): void {
    $factory = new FakeMoneyFactory();
    $service = new \Markommerce\Catalog\Service\ProductPriceService($factory);

    $product = new Product();
    $product->basePriceAmount = 4999;

    $service->getBasePrice($product);

    expect($factory->calls)->toHaveCount(1)
        ->and($factory->calls[0]['amount'])->toBe(4999);
});

it('passes no currency argument to MoneyFactory so the factory\'s default currency is used', function (): void {
    $factory = new FakeMoneyFactory();
    $service = new \Markommerce\Catalog\Service\ProductPriceService($factory);

    $product = new Product();
    $product->basePriceAmount = 1000;

    $service->getBasePrice($product);

    expect($factory->calls)->toHaveCount(1)
        ->and($factory->calls[0]['currency'])->toBeNull();
});

it('returns a MoneyInterface whose amount matches the product basePriceAmount', function (): void {
    $factory = new FakeMoneyFactory();
    $service = new \Markommerce\Catalog\Service\ProductPriceService($factory);

    $product = new Product();
    $product->basePriceAmount = 2500;

    $money = $service->getBasePrice($product);

    expect($money)->toBeInstanceOf(MoneyInterface::class)
        ->and($money->amount())->toBe(2500);
});

it('carries a multi-store refactor docblock on the default implementation', function (): void {
    $reflection = new ReflectionClass(\Markommerce\Catalog\Service\ProductPriceService::class);
    $docComment = $reflection->getDocComment();

    expect($docComment)->not->toBeFalse()
        ->and($docComment)->toContain('@todo multi-store');
});

it('does not construct any concrete Money class directly', function (): void {
    $reflection = new ReflectionClass(\Markommerce\Catalog\Service\ProductPriceService::class);
    $path = $reflection->getFileName();

    expect($path)->toBeString();

    $serviceFile = file_get_contents((string) $path);

    expect($serviceFile)->toBeString()
        ->and($serviceFile)->not->toMatch('/new\s+\w*Money\b/');
});
