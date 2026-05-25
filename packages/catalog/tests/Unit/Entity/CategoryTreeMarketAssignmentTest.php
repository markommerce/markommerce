<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Markommerce\Catalog\Entity\CategoryTreeMarketAssignment;

it('can be instantiated with default values', function (): void {
    $assignment = new CategoryTreeMarketAssignment();

    expect($assignment)->toBeInstanceOf(CategoryTreeMarketAssignment::class);
});

it('exposes market and treeId public properties', function (): void {
    $reflection = new ReflectionClass(CategoryTreeMarketAssignment::class);

    expect($reflection->hasProperty('market'))->toBeTrue()
        ->and($reflection->hasProperty('treeId'))->toBeTrue();
});

it('uses market as the primary key column', function (): void {
    $reflection = new ReflectionClass(CategoryTreeMarketAssignment::class);
    $property = $reflection->getProperty('market');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $column = $attributes[0]->newInstance();

    expect($column->primaryKey)->toBeTrue()
        ->and($column->length)->toBe(64);
});

it('is mapped to the catalog_category_tree_market_assignments table', function (): void {
    $reflection = new ReflectionClass(CategoryTreeMarketAssignment::class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1);

    $table = $attributes[0]->newInstance();

    expect($table->name)->toBe('catalog_category_tree_market_assignments');
});
