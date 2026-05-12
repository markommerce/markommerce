<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Unit\Entity;

use Marko\Database\Attributes\BelongsToMany;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Entity\ProductCategory;
use ReflectionClass;

it('maps to the products table via the Table attribute', function (): void {
    $reflection = new ReflectionClass(Product::class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1);

    $tableAttribute = $attributes[0]->newInstance();
    expect($tableAttribute->name)->toBe('products');
});

it('exposes id as a nullable auto-increment primary key column', function (): void {
    $reflection = new ReflectionClass(Product::class);

    expect($reflection->hasProperty('id'))->toBeTrue();

    $property = $reflection->getProperty('id');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->primaryKey)->toBeTrue()
        ->and($columnAttribute->autoIncrement)->toBeTrue()
        ->and($property->getType()->allowsNull())->toBeTrue();
});

it('exposes sku as a unique string column with length 255', function (): void {
    $reflection = new ReflectionClass(Product::class);

    expect($reflection->hasProperty('sku'))->toBeTrue();

    $property = $reflection->getProperty('sku');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->length)->toBe(255)
        ->and($columnAttribute->unique)->toBeTrue()
        ->and($property->getType()->getName())->toBe('string')
        ->and($property->getType()->allowsNull())->toBeFalse();
});

it('exposes name as a required string column with length 255', function (): void {
    $reflection = new ReflectionClass(Product::class);

    expect($reflection->hasProperty('name'))->toBeTrue();

    $property = $reflection->getProperty('name');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->length)->toBe(255)
        ->and($property->getType()->getName())->toBe('string')
        ->and($property->getType()->allowsNull())->toBeFalse();
});

it('exposes basePriceAmount as a BIGINT column (uppercase type string)', function (): void {
    $reflection = new ReflectionClass(Product::class);

    expect($reflection->hasProperty('basePriceAmount'))->toBeTrue();

    $property = $reflection->getProperty('basePriceAmount');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->type)->toBe('BIGINT')
        ->and($property->getType()->getName())->toBe('int')
        ->and($property->getType()->allowsNull())->toBeFalse();
});

it('declares a BelongsToMany categories relationship through ProductCategory with foreignKey productId and relatedKey categoryId', function (): void {
    $reflection = new ReflectionClass(Product::class);

    expect($reflection->hasProperty('categories'))->toBeTrue();

    $property = $reflection->getProperty('categories');
    $attributes = $property->getAttributes(BelongsToMany::class);

    expect($attributes)->toHaveCount(1);

    $btmAttribute = $attributes[0]->newInstance();
    expect($btmAttribute->entityClass)->toBe(Category::class)
        ->and($btmAttribute->pivotClass)->toBe(ProductCategory::class)
        ->and($btmAttribute->foreignKey)->toBe('productId')
        ->and($btmAttribute->relatedKey)->toBe('categoryId');
});

it('does not declare a getBasePrice or setBasePrice method on the entity', function (): void {
    $reflection = new ReflectionClass(Product::class);

    expect($reflection->hasMethod('getBasePrice'))->toBeFalse()
        ->and($reflection->hasMethod('setBasePrice'))->toBeFalse();
});

it('does not declare createdAt or updatedAt columns', function (): void {
    $reflection = new ReflectionClass(Product::class);

    expect($reflection->hasProperty('createdAt'))->toBeFalse()
        ->and($reflection->hasProperty('updatedAt'))->toBeFalse();
});

it('carries multi-store refactor docblocks on name and basePriceAmount', function (): void {
    $reflection = new ReflectionClass(Product::class);

    $nameProperty = $reflection->getProperty('name');
    expect($nameProperty->getDocComment())->toContain('TODO multi-store');

    $basePriceProperty = $reflection->getProperty('basePriceAmount');
    expect($basePriceProperty->getDocComment())->toContain('TODO multi-store');
});

it('can be constructed and have its public properties assigned directly', function (): void {
    $product = new Product();
    $product->id = 1;
    $product->sku = 'SKU-001';
    $product->name = 'Test Product';
    $product->basePriceAmount = 1999;

    expect($product->id)->toBe(1)
        ->and($product->sku)->toBe('SKU-001')
        ->and($product->name)->toBe('Test Product')
        ->and($product->basePriceAmount)->toBe(1999)
        ->and($product->categories)->toBe([])
        ->and($product)->toBeInstanceOf(Entity::class);
});
