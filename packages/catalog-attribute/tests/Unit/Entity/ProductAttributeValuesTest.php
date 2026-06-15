<?php

declare(strict_types=1);

use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogAttribute\Entity\ProductAttributeValues;

it('maps ProductAttributeValues to the catalog_products table via the attribute_values column', function (): void {
    $factory = new EntityMetadataFactory();
    $factory->linkExtendersFrom([
        Product::class,
        ProductAttributeValues::class,
    ]);

    $metadata = $factory->parse(Product::class);

    expect($metadata->extenders)->toContain(ProductAttributeValues::class);

    $extenderMetadata = $factory->parse(ProductAttributeValues::class);

    expect($extenderMetadata->tableName)->toBe('catalog_products');

    $columnNames = array_map(fn ($col) => $col->name, $extenderMetadata->columns);

    expect($columnNames)->toContain('attribute_values');
});

it('sets and gets a value by code', function (): void {
    $entity = new ProductAttributeValues();

    $entity->set('color', 'red');

    expect($entity->get('color'))->toBe('red');
});

it('reports whether a code has a stored value', function (): void {
    $entity = new ProductAttributeValues();

    expect($entity->has('size'))->toBeFalse();

    $entity->set('size', 'M');

    expect($entity->has('size'))->toBeTrue();
});

it('returns all stored values as a code-keyed map', function (): void {
    $entity = new ProductAttributeValues();

    $entity->set('color', 'blue');
    $entity->set('size', 'L');

    expect($entity->all())->toBe(['color' => 'blue', 'size' => 'L']);
});

it('clears a stored value and nulls the column when empty', function (): void {
    $entity = new ProductAttributeValues();

    $entity->set('color', 'green');
    $entity->clear('color');

    expect($entity->has('color'))->toBeFalse()
        ->and($entity->values)->toBeNull();
});
