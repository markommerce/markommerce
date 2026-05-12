<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Unit\Repository;

use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;
use Marko\Database\Repository\RepositoryInterface;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Repository\ProductRepository;
use Markommerce\Catalog\Repository\ProductRepositoryInterface;
use Markommerce\Catalog\Tests\Support\FakeConnection;
use ReflectionClass;

it('declares ProductRepositoryInterface extending RepositoryInterface generic over Product', function (): void {
    $reflection = new ReflectionClass(ProductRepositoryInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->implementsInterface(RepositoryInterface::class))->toBeTrue();

    $docComment = $reflection->getDocComment();
    expect($docComment)->toContain('@template')
        ->and($docComment)->toContain('@extends RepositoryInterface<');
});

it('declares a findBySku method on ProductRepositoryInterface returning a nullable Product', function (): void {
    $reflection = new ReflectionClass(ProductRepositoryInterface::class);

    expect($reflection->hasMethod('findBySku'))->toBeTrue();

    $method = $reflection->getMethod('findBySku');
    $params = $method->getParameters();

    expect($params)->toHaveCount(1)
        ->and($params[0]->getName())->toBe('sku')
        ->and((string) $params[0]->getType())->toBe('string');

    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull();
    expect((string) $returnType)->toBe('?' . Product::class);
});

it('implements ProductRepositoryInterface in ProductRepository', function (): void {
    $reflection = new ReflectionClass(ProductRepository::class);

    expect($reflection->isInterface())->toBeFalse()
        ->and($reflection->implementsInterface(ProductRepositoryInterface::class))->toBeTrue()
        ->and($reflection->isSubclassOf(Repository::class))->toBeTrue();
});

it('sets ENTITY_CLASS to the Product fully qualified class name', function (): void {
    $reflection = new ReflectionClass(ProductRepository::class);

    expect($reflection->hasConstant('ENTITY_CLASS'))->toBeTrue()
        ->and($reflection->getConstant('ENTITY_CLASS'))->toBe(Product::class);
});

it('returns null from findBySku when no product matches', function (): void {
    $connection = new FakeConnection();
    $connection->nextQueryResult = [];

    $repository = new ProductRepository(
        $connection,
        new EntityMetadataFactory(),
        new EntityHydrator(),
    );

    $result = $repository->findBySku('NONEXISTENT-SKU');

    expect($result)->toBeNull();
});

it('returns the matching product from findBySku when the sku exists', function (): void {
    $connection = new FakeConnection();
    $connection->nextQueryResult = [
        [
            'id' => 1,
            'sku' => 'PROD-001',
            'name' => 'Test Product',
            'base_price_amount' => 1999,
        ],
    ];

    $repository = new ProductRepository(
        $connection,
        new EntityMetadataFactory(),
        new EntityHydrator(),
    );

    $result = $repository->findBySku('PROD-001');

    expect($result)->toBeInstanceOf(Product::class);
    assert($result instanceof Product);
    expect($result->sku)->toBe('PROD-001')
        ->and($result->name)->toBe('Test Product')
        ->and($result->basePriceAmount)->toBe(1999);
});
