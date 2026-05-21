<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Storage\HasScopesInterface;

it('maps the Category entity to the catalog_categories table', function (): void {
    $reflection = new ReflectionClass(Category::class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1);

    $table = $attributes[0]->newInstance();

    expect($table->name)->toBe('catalog_categories');
});

it('exposes an auto-increment integer primary key id', function (): void {
    $reflection = new ReflectionClass(Category::class);
    $property = $reflection->getProperty('id');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $column = $attributes[0]->newInstance();

    expect($column->primaryKey)->toBeTrue()
        ->and($column->autoIncrement)->toBeTrue();

    $category = new Category();
    expect($category->id)->toBeNull();
});

it('marks the name property as scoped on the locale axis', function (): void {
    $reflection = new ReflectionClass(Category::class);
    $property = $reflection->getProperty('name');
    $attributes = $property->getAttributes(Scoped::class);

    expect($attributes)->toHaveCount(1);

    $scoped = $attributes[0]->newInstance();

    expect($scoped->axes)->toBe(['locale']);
});

it('marks the description property as scoped on the locale axis', function (): void {
    $reflection = new ReflectionClass(Category::class);
    $property = $reflection->getProperty('description');
    $attributes = $property->getAttributes(Scoped::class);

    expect($attributes)->toHaveCount(1);

    $scoped = $attributes[0]->newInstance();

    expect($scoped->axes)->toBe(['locale']);
});

it('implements HasScopesInterface', function (): void {
    $category = new Category();

    expect($category)->toBeInstanceOf(HasScopesInterface::class);
});

it('exposes a scopes storage column via the HasScopes trait', function (): void {
    $reflection = new ReflectionClass(Category::class);

    expect($reflection->hasProperty('scopes'))->toBeTrue();

    $property = $reflection->getProperty('scopes');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $column = $attributes[0]->newInstance();

    expect($column->name)->toBe('scopes')
        ->and($column->type)->toBe('json')
        ->and($column->nullable)->toBeTrue();

    $category = new Category();
    expect($category->scopes)->toBeNull();
});
