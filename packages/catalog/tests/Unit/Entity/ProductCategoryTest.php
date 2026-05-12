<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Unit\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Catalog\Entity\ProductCategory;
use ReflectionClass;

it('maps to the product_categories table via the Table attribute', function (): void {
    $reflection = new ReflectionClass(ProductCategory::class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1);

    $tableAttribute = $attributes[0]->newInstance();
    expect($tableAttribute->name)->toBe('product_categories');
});

it('exposes id as a nullable auto-increment primary key', function (): void {
    $reflection = new ReflectionClass(ProductCategory::class);

    expect($reflection->hasProperty('id'))->toBeTrue();

    $property = $reflection->getProperty('id');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->primaryKey)->toBeTrue()
        ->and($columnAttribute->autoIncrement)->toBeTrue()
        ->and($property->getType()->allowsNull())->toBeTrue();
});

it('exposes productId with a Column reference to products.id and onDelete cascade', function (): void {
    $reflection = new ReflectionClass(ProductCategory::class);

    expect($reflection->hasProperty('productId'))->toBeTrue();

    $property = $reflection->getProperty('productId');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->references)->toBe('products.id')
        ->and($columnAttribute->onDelete)->toBe('CASCADE');
});

it('exposes categoryId with a Column reference to categories.id and onDelete cascade', function (): void {
    $reflection = new ReflectionClass(ProductCategory::class);

    expect($reflection->hasProperty('categoryId'))->toBeTrue();

    $property = $reflection->getProperty('categoryId');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->references)->toBe('categories.id')
        ->and($columnAttribute->onDelete)->toBe('CASCADE');
});

it('declares a unique class-level Index covering product_id and category_id', function (): void {
    $reflection = new ReflectionClass(ProductCategory::class);
    $attributes = $reflection->getAttributes(Index::class);

    expect($attributes)->toHaveCount(1);

    $indexAttribute = $attributes[0]->newInstance();
    expect($indexAttribute->name)->toBe('idx_product_categories_unique')
        ->and($indexAttribute->columns)->toBe(['product_id', 'category_id'])
        ->and($indexAttribute->unique)->toBeTrue();
});

it('can be constructed and have its public properties assigned directly', function (): void {
    $productCategory = new ProductCategory();
    $productCategory->id = 1;
    $productCategory->productId = 10;
    $productCategory->categoryId = 20;

    expect($productCategory->id)->toBe(1)
        ->and($productCategory->productId)->toBe(10)
        ->and($productCategory->categoryId)->toBe(20);
});

it('satisfies Marko\'s BelongsToMany pivotClass contract by extending Entity', function (): void {
    $productCategory = new ProductCategory();

    expect($productCategory)->toBeInstanceOf(Entity::class);
});
