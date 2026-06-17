<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogAttributeScope\Entity\ProductScopedAttributeValues;
use Markommerce\Scope\Storage\HasScopesInterface;

it(
    'maps ProductScopedAttributeValues to the catalog_products table via the scoped_attribute_values column',
    function (): void {
        $reflection = new ReflectionClass(ProductScopedAttributeValues::class);
        $tableAttributes = $reflection->getAttributes(Table::class);

        expect($tableAttributes)->toHaveCount(1);

        $table = $tableAttributes[0]->newInstance();

        expect($table->extends)->toBe(Product::class);

        $columnProperties = array_filter(
            $reflection->getProperties(ReflectionProperty::IS_PUBLIC),
            fn (ReflectionProperty $p) => count($p->getAttributes(Column::class)) > 0,
        );

        $columnsByName = [];
        foreach ($columnProperties as $prop) {
            $colAttr = $prop->getAttributes(Column::class)[0]->newInstance();
            $columnsByName[$colAttr->name] = $prop;
        }

        expect($columnsByName)->toHaveKey('scoped_attribute_values');
    },
);

it('sets and reads a value override for a signature and code', function (): void {
    $entity = new ProductScopedAttributeValues();
    $entity->setOverride('locale:en', 'color', 'red');

    expect($entity->override('locale:en', 'color'))->toBe('red');
});

it('reports whether a signature and code has an override', function (): void {
    $entity = new ProductScopedAttributeValues();
    $entity->setOverride('locale:fr', 'size', 'L');

    expect($entity->hasOverride('locale:fr', 'size'))->toBeTrue()
        ->and($entity->hasOverride('locale:fr', 'weight'))->toBeFalse()
        ->and($entity->hasOverride('locale:de', 'size'))->toBeFalse();
});

it('clears an override and nulls the column when empty', function (): void {
    $entity = new ProductScopedAttributeValues();
    $entity->setOverride('locale:en', 'color', 'blue');
    $entity->clearOverride('locale:en', 'color');

    expect($entity->scopedValues)->toBeNull()
        ->and($entity->hasOverride('locale:en', 'color'))->toBeFalse();
});

it('implements HasScopesInterface', function (): void {
    $entity = new ProductScopedAttributeValues();

    expect($entity)->toBeInstanceOf(HasScopesInterface::class)
        ->and($entity)->toBeInstanceOf(Entity::class);
});

it('uses a distinct column name from the catalog-scope scopes column', function (): void {
    $reflection = new ReflectionClass(ProductScopedAttributeValues::class);

    $columnProperties = array_filter(
        $reflection->getProperties(ReflectionProperty::IS_PUBLIC),
        fn (ReflectionProperty $p) => count($p->getAttributes(Column::class)) > 0,
    );

    $columnNames = [];
    foreach ($columnProperties as $prop) {
        $colAttr = $prop->getAttributes(Column::class)[0]->newInstance();
        $columnNames[] = $colAttr->name;
    }

    expect($columnNames)->not->toContain('scopes')
        ->and($columnNames)->toContain('scoped_attribute_values');
});
