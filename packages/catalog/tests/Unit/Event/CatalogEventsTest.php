<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Unit\Event;

use Marko\Core\Event\Event;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Event\CategoryCreated;
use Markommerce\Catalog\Event\CategoryDeleted;
use Markommerce\Catalog\Event\CategoryUpdated;
use Markommerce\Catalog\Event\ProductAssignedToCategory;
use Markommerce\Catalog\Event\ProductCreated;
use Markommerce\Catalog\Event\ProductDeleted;
use Markommerce\Catalog\Event\ProductRemovedFromCategory;
use Markommerce\Catalog\Event\ProductUpdated;
use ReflectionClass;
use ReflectionProperty;

it('exposes the affected product on ProductCreated and ProductUpdated', function (): void {
    $product = new Product();
    $product->id = 1;
    $product->sku = 'SKU-001';
    $product->name = 'Test Product';
    $product->basePriceAmount = 1999;

    $created = new ProductCreated($product);
    $updated = new ProductUpdated($product);

    expect($created->product)->toBe($product)
        ->and($updated->product)->toBe($product);
});

it('exposes the deleted product id on ProductDeleted', function (): void {
    $deleted = new ProductDeleted(42);

    expect($deleted->productId)->toBe(42);
});

it('exposes the affected category on CategoryCreated and CategoryUpdated', function (): void {
    $category = new Category();
    $category->id = 5;
    $category->name = 'Test Category';

    $created = new CategoryCreated($category);
    $updated = new CategoryUpdated($category);

    expect($created->category)->toBe($category)
        ->and($updated->category)->toBe($category);
});

it('exposes the deleted category id on CategoryDeleted', function (): void {
    $deleted = new CategoryDeleted(99);

    expect($deleted->categoryId)->toBe(99);
});

it('exposes both product id and category id on ProductAssignedToCategory and ProductRemovedFromCategory', function (): void {
    $assigned = new ProductAssignedToCategory(10, 20);
    $removed = new ProductRemovedFromCategory(10, 20);

    expect($assigned->productId)->toBe(10)
        ->and($assigned->categoryId)->toBe(20)
        ->and($removed->productId)->toBe(10)
        ->and($removed->categoryId)->toBe(20);
});

it('has every event extend Marko\Core\Event\Event so it can be passed to EventDispatcherInterface::dispatch', function (): void {
    $product = new Product();
    $product->sku = 'SKU-001';
    $product->name = 'Test';
    $product->basePriceAmount = 0;

    $category = new Category();
    $category->name = 'Cat';

    $events = [
        new ProductCreated($product),
        new ProductUpdated($product),
        new ProductDeleted(1),
        new CategoryCreated($category),
        new CategoryUpdated($category),
        new CategoryDeleted(1),
        new ProductAssignedToCategory(1, 2),
        new ProductRemovedFromCategory(1, 2),
    ];

    foreach ($events as $event) {
        expect($event)->toBeInstanceOf(Event::class);
    }
});

it('declares promoted constructor properties as readonly and public', function (): void {
    $eventClasses = [
        [ProductCreated::class, ['product']],
        [ProductUpdated::class, ['product']],
        [ProductDeleted::class, ['productId']],
        [CategoryCreated::class, ['category']],
        [CategoryUpdated::class, ['category']],
        [CategoryDeleted::class, ['categoryId']],
        [ProductAssignedToCategory::class, ['productId', 'categoryId']],
        [ProductRemovedFromCategory::class, ['productId', 'categoryId']],
    ];

    foreach ($eventClasses as [$class, $properties]) {
        $reflection = new ReflectionClass($class);
        foreach ($properties as $propertyName) {
            $property = $reflection->getProperty($propertyName);
            expect($property->isPublic())->toBeTrue("Property {$propertyName} on {$class} should be public")
                ->and($property->isReadOnly())->toBeTrue("Property {$propertyName} on {$class} should be readonly");
        }
    }
});
