<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Markommerce\Catalog\Entity\ProductCategoryAssignment;

it('maps the assignment entity to the catalog_product_category table', function (): void {
    $reflection = new ReflectionClass(ProductCategoryAssignment::class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1);

    $table = $attributes[0]->newInstance();

    expect($table->name)->toBe('catalog_product_category');
});

it('exposes an auto-increment integer primary key id', function (): void {
    $reflection = new ReflectionClass(ProductCategoryAssignment::class);
    $property = $reflection->getProperty('id');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $column = $attributes[0]->newInstance();

    expect($column->primaryKey)->toBeTrue()
        ->and($column->autoIncrement)->toBeTrue();

    $assignment = new ProductCategoryAssignment();
    expect($assignment->id)->toBeNull();
});

it('has a product_id column referencing the catalog_products table', function (): void {
    $reflection = new ReflectionClass(ProductCategoryAssignment::class);
    $property = $reflection->getProperty('productId');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $column = $attributes[0]->newInstance();

    expect($column->name)->toBe('product_id')
        ->and($column->references)->toBe('catalog_products')
        ->and($column->onDelete)->toBe('CASCADE');
});

it('has a category_id column referencing the catalog_categories table', function (): void {
    $reflection = new ReflectionClass(ProductCategoryAssignment::class);
    $property = $reflection->getProperty('categoryId');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $column = $attributes[0]->newInstance();

    expect($column->name)->toBe('category_id')
        ->and($column->references)->toBe('catalog_categories')
        ->and($column->onDelete)->toBe('CASCADE');
});

it('declares a composite unique index over product_id and category_id', function (): void {
    $reflection = new ReflectionClass(ProductCategoryAssignment::class);
    $attributes = $reflection->getAttributes(Index::class);

    expect($attributes)->toHaveCount(1);

    $index = $attributes[0]->newInstance();

    expect($index->name)->toBe('uniq_catalog_product_category')
        ->and($index->columns)->toBe(['product_id', 'category_id'])
        ->and($index->unique)->toBeTrue();
});

it('exposes a position property defaulting to zero on a new assignment', function (): void {
    $assignment = new ProductCategoryAssignment();

    expect($assignment->position)->toBe(0);
});

it('maps the position property to the position column', function (): void {
    $reflection = new ReflectionClass(ProductCategoryAssignment::class);
    $property = $reflection->getProperty('position');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $column = $attributes[0]->newInstance();

    expect($column->name)->toBe('position')
        ->and($column->type)->toBe('integer')
        ->and($column->nullable)->toBeFalse();
});

it('does not implement HasScopesInterface', function (): void {
    $hasScopesInterface = 'Markommerce\\Scope\\Storage\\HasScopesInterface';
    $assignment = new ProductCategoryAssignment();

    $implements = interface_exists($hasScopesInterface)
        ? ($assignment instanceof $hasScopesInterface)
        : false;

    expect($implements)->toBeFalse();
});
