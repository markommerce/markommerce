<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Unit\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Catalog\Entity\Category;
use ReflectionClass;

it('maps to the categories table via the Table attribute', function (): void {
    $reflection = new ReflectionClass(Category::class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1);

    $tableAttribute = $attributes[0]->newInstance();
    expect($tableAttribute->name)->toBe('categories');
});

it('exposes id as a nullable auto-increment primary key column', function (): void {
    $reflection = new ReflectionClass(Category::class);

    expect($reflection->hasProperty('id'))->toBeTrue();

    $property = $reflection->getProperty('id');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->primaryKey)->toBeTrue()
        ->and($columnAttribute->autoIncrement)->toBeTrue()
        ->and($property->getType()->allowsNull())->toBeTrue();
});

it('exposes name as a required string column with length 255', function (): void {
    $reflection = new ReflectionClass(Category::class);

    expect($reflection->hasProperty('name'))->toBeTrue();

    $property = $reflection->getProperty('name');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->length)->toBe(255)
        ->and($property->getType()->getName())->toBe('string')
        ->and($property->getType()->allowsNull())->toBeFalse();
});

it('carries a multi-store refactor docblock on the name property', function (): void {
    $reflection = new ReflectionClass(Category::class);
    $property = $reflection->getProperty('name');
    $docComment = $property->getDocComment();

    expect($docComment)->toContain('TODO multi-store');
});

it('can be constructed and have its public properties assigned directly', function (): void {
    $category = new Category();
    $category->id = 42;
    $category->name = 'Electronics';

    expect($category->id)->toBe(42)
        ->and($category->name)->toBe('Electronics')
        ->and($category)->toBeInstanceOf(Entity::class);
});
