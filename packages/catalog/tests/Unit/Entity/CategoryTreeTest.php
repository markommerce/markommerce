<?php

declare(strict_types=1);

use Marko\Database\Attributes\Table;
use Markommerce\Catalog\Entity\CategoryTree;

it('can be instantiated with default values', function (): void {
    $tree = new CategoryTree();

    expect($tree)->toBeInstanceOf(CategoryTree::class);
});

it('exposes id, code, name, and isDefault public properties', function (): void {
    $reflection = new ReflectionClass(CategoryTree::class);

    expect($reflection->hasProperty('id'))->toBeTrue()
        ->and($reflection->hasProperty('code'))->toBeTrue()
        ->and($reflection->hasProperty('name'))->toBeTrue()
        ->and($reflection->hasProperty('isDefault'))->toBeTrue();
});

it('defaults isDefault to false', function (): void {
    $tree = new CategoryTree();

    expect($tree->isDefault)->toBeFalse();
});

it('defaults id to null until persisted', function (): void {
    $tree = new CategoryTree();

    expect($tree->id)->toBeNull();
});

it('is mapped to the catalog_category_trees table', function (): void {
    $reflection = new ReflectionClass(CategoryTree::class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1);

    $table = $attributes[0]->newInstance();

    expect($table->name)->toBe('catalog_category_trees');
});
