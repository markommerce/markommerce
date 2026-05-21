<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Storage\HasScopesInterface;

it('maps the Product entity to the catalog_products table', function (): void {
    $reflection = new ReflectionClass(Product::class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1);

    $table = $attributes[0]->newInstance();

    expect($table->name)->toBe('catalog_products');
});

it('exposes an auto-increment integer primary key id', function (): void {
    $reflection = new ReflectionClass(Product::class);
    $property = $reflection->getProperty('id');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $column = $attributes[0]->newInstance();

    expect($column->primaryKey)->toBeTrue()
        ->and($column->autoIncrement)->toBeTrue();

    $product = new Product();
    expect($product->id)->toBeNull();
});

it('declares the sku column as unique', function (): void {
    $reflection = new ReflectionClass(Product::class);
    $property = $reflection->getProperty('sku');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $column = $attributes[0]->newInstance();

    expect($column->unique)->toBeTrue()
        ->and($column->length)->toBe(64);

    $product = new Product();
    expect($product->sku)->toBe('');
});

it('does not mark the sku property as scoped', function (): void {
    $reflection = new ReflectionClass(Product::class);
    $property = $reflection->getProperty('sku');
    $attributes = $property->getAttributes(Scoped::class);

    expect($attributes)->toHaveCount(0);
});

it('marks the name property as scoped on the locale axis', function (): void {
    $reflection = new ReflectionClass(Product::class);
    $property = $reflection->getProperty('name');
    $attributes = $property->getAttributes(Scoped::class);

    expect($attributes)->toHaveCount(1);

    $scoped = $attributes[0]->newInstance();

    expect($scoped->axes)->toBe(['locale']);
});

it('marks the description property as scoped on the locale axis', function (): void {
    $reflection = new ReflectionClass(Product::class);
    $property = $reflection->getProperty('description');
    $attributes = $property->getAttributes(Scoped::class);

    expect($attributes)->toHaveCount(1);

    $scoped = $attributes[0]->newInstance();

    expect($scoped->axes)->toBe(['locale']);
});

it('implements HasScopesInterface and exposes a scopes storage column', function (): void {
    $product = new Product();

    expect($product)->toBeInstanceOf(HasScopesInterface::class);

    $reflection = new ReflectionClass(Product::class);

    expect($reflection->hasProperty('scopes'))->toBeTrue();

    $property = $reflection->getProperty('scopes');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $column = $attributes[0]->newInstance();

    expect($column->name)->toBe('scopes')
        ->and($column->type)->toBe('json')
        ->and($column->nullable)->toBeTrue();

    expect($product->scopes)->toBeNull();
});
