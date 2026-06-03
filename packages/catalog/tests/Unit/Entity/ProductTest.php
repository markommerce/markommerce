<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Markommerce\Catalog\Entity\Product;

it('maps the Product entity to the catalog_products table', function (): void {
    $reflection = new ReflectionClass(Product::class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1);

    $table = $attributes[0]->newInstance();

    expect($table->name)->toBe('catalog_products');
});

it('exposes an auto-increment integer primary key id on Product', function (): void {
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

it('declares the sku column as unique on Product', function (): void {
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

it('does not implement HasScopesInterface on Product', function (): void {
    $hasScopesInterface = 'Markommerce\\Scope\\Storage\\HasScopesInterface';
    $product = new Product();

    $implements = interface_exists($hasScopesInterface)
        ? ($product instanceof $hasScopesInterface)
        : false;

    expect($implements)->toBeFalse();
});

it('does not declare a scopes column on the Product entity reflection', function (): void {
    $reflection = new ReflectionClass(Product::class);

    expect($reflection->hasProperty('scopes'))->toBeFalse();
});

it('has no #[Scoped] attributes on any Product property', function (): void {
    $scopedClass = 'Markommerce\\Scope\\Attributes\\Scoped';
    $reflection = new ReflectionClass(Product::class);

    foreach ($reflection->getProperties() as $property) {
        $scopedAttrs = array_filter(
            $property->getAttributes(),
            fn ($attr) => $attr->getName() === $scopedClass,
        );
        expect(count($scopedAttrs))->toBe(0);
    }
});

it('has no Markommerce\\Scope namespace imports in the Product class file', function (): void {
    $file = (new ReflectionClass(Product::class))->getFileName();
    $contents = file_get_contents($file);

    expect($contents)->not->toContain('Markommerce\\Scope');
});

it('stores a product price amount as a decimal string', function (): void {
    $product = new Product();
    $product->priceAmount = '19.9900';

    expect($product->priceAmount)->toBe('19.9900');
});

it('allows a product to have no price amount set', function (): void {
    $product = new Product();

    expect($product->priceAmount)->toBeNull();
});

it('preserves price amount precision without floating point drift', function (): void {
    $product = new Product();
    $product->priceAmount = '9.9999';

    expect($product->priceAmount)->toBe('9.9999');
    expect($product->priceAmount)->not->toBe(10.0);
    expect(is_string($product->priceAmount))->toBeTrue();
});

it('maps the price amount property to the price_amount column', function (): void {
    $reflection = new ReflectionClass(Product::class);
    $property = $reflection->getProperty('priceAmount');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $column = $attributes[0]->newInstance();

    expect($column->name)->toBe('price_amount')
        ->and($column->type)->toBe('decimal(20,4)')
        ->and($column->nullable)->toBeTrue();
});
