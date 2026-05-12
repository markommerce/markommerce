<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Unit\Service;

use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Event\ProductAssignedToCategory;
use Markommerce\Catalog\Event\ProductRemovedFromCategory;
use Markommerce\Catalog\Exception\CategoryNotFoundException;
use Markommerce\Catalog\Exception\ProductNotFoundException;
use Markommerce\Catalog\Service\CategoryAssignmentService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeConnection;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;
use Markommerce\Catalog\Tests\Support\RecordingEventDispatcher;
use RuntimeException;

function makeProduct(int $id): Product
{
    $product = new Product();
    $product->id = $id;
    $product->sku = "SKU-{$id}";
    $product->name = "Product {$id}";
    $product->basePriceAmount = 100;

    return $product;
}

function makeCategory(int $id): Category
{
    $category = new Category();
    $category->id = $id;
    $category->name = "Category {$id}";

    return $category;
}

it('assigns a product to a category and dispatches ProductAssignedToCategory', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $connection = new FakeConnection();
    $dispatcher = new RecordingEventDispatcher();

    $product = makeProduct(1);
    $category = makeCategory(2);
    $productRepo->products[] = $product;
    $categoryRepo->categories[] = $category;

    // No existing assignment
    $connection->nextQueryResult = [];

    $service = new CategoryAssignmentService(
        $productRepo,
        $categoryRepo,
        $connection,
        $connection,
        $dispatcher,
    );

    $service->assign(1, 2);

    expect($dispatcher->events)->toHaveCount(1)
        ->and($dispatcher->events[0])->toBeInstanceOf(ProductAssignedToCategory::class);
    assert($dispatcher->events[0] instanceof ProductAssignedToCategory);
    expect($dispatcher->events[0]->productId)->toBe(1)
        ->and($dispatcher->events[0]->categoryId)->toBe(2);
});

it('wraps the assign select-then-insert in a transaction and commits on success', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $connection = new FakeConnection();

    $product = makeProduct(1);
    $category = makeCategory(2);
    $productRepo->products[] = $product;
    $categoryRepo->categories[] = $category;
    $connection->nextQueryResult = [];

    $service = new CategoryAssignmentService(
        $productRepo,
        $categoryRepo,
        $connection,
        $connection,
    );

    $service->assign(1, 2);

    expect($connection->transactionLog)->toContain('begin')
        ->and($connection->transactionLog)->toContain('commit')
        ->and($connection->transactionLog)->not->toContain('rollback');
});

it('rolls back the transaction in assign when the insert throws', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $connection = new FakeConnection();

    $product = makeProduct(1);
    $category = makeCategory(2);
    $productRepo->products[] = $product;
    $categoryRepo->categories[] = $category;
    $connection->nextQueryResult = [];
    $connection->throwOnExecute = new RuntimeException('DB error');

    $service = new CategoryAssignmentService(
        $productRepo,
        $categoryRepo,
        $connection,
        $connection,
    );

    expect(fn () => $service->assign(1, 2))->toThrow(RuntimeException::class);
    expect($connection->transactionLog)->toContain('begin')
        ->and($connection->transactionLog)->toContain('rollback')
        ->and($connection->transactionLog)->not->toContain('commit');
});

it('is idempotent when the same assignment is created twice and does not redispatch the event', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $connection = new FakeConnection();
    $dispatcher = new RecordingEventDispatcher();

    $product = makeProduct(1);
    $category = makeCategory(2);
    $productRepo->products[] = $product;
    $categoryRepo->categories[] = $category;

    // Assignment already exists
    $connection->nextQueryResult = [['exists' => '1']];

    $service = new CategoryAssignmentService(
        $productRepo,
        $categoryRepo,
        $connection,
        $connection,
        $dispatcher,
    );

    $service->assign(1, 2);

    expect($dispatcher->events)->toBeEmpty();
});

it('throws ProductNotFoundException from assign when the product does not exist before consulting the category', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $connection = new FakeConnection();

    // Product NOT in repo
    $category = makeCategory(2);
    $categoryRepo->categories[] = $category;

    $service = new CategoryAssignmentService(
        $productRepo,
        $categoryRepo,
        $connection,
        $connection,
    );

    expect(fn () => $service->assign(99, 2))->toThrow(ProductNotFoundException::class);
});

it('throws CategoryNotFoundException from assign when the product exists but the category does not', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $connection = new FakeConnection();

    $product = makeProduct(1);
    $productRepo->products[] = $product;
    // Category NOT in repo

    $service = new CategoryAssignmentService(
        $productRepo,
        $categoryRepo,
        $connection,
        $connection,
    );

    expect(fn () => $service->assign(1, 99))->toThrow(CategoryNotFoundException::class);
});

it('unassigns an existing assignment and dispatches ProductRemovedFromCategory only when execute reports a non-zero affected-row count', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $connection = new FakeConnection();
    $dispatcher = new RecordingEventDispatcher();

    $connection->executeReturnValue = 1;

    $service = new CategoryAssignmentService(
        $productRepo,
        $categoryRepo,
        $connection,
        $connection,
        $dispatcher,
    );

    $service->unassign(1, 2);

    expect($dispatcher->events)->toHaveCount(1)
        ->and($dispatcher->events[0])->toBeInstanceOf(ProductRemovedFromCategory::class);
    assert($dispatcher->events[0] instanceof ProductRemovedFromCategory);
    expect($dispatcher->events[0]->productId)->toBe(1)
        ->and($dispatcher->events[0]->categoryId)->toBe(2);
});

it('is a no-op when unassigning a non-existent assignment and does not dispatch', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $connection = new FakeConnection();
    $dispatcher = new RecordingEventDispatcher();

    $connection->executeReturnValue = 0;

    $service = new CategoryAssignmentService(
        $productRepo,
        $categoryRepo,
        $connection,
        $connection,
        $dispatcher,
    );

    $service->unassign(1, 2);

    expect($dispatcher->events)->toBeEmpty();
});

it('returns the categories for a product via getCategoriesForProduct', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $connection = new FakeConnection();

    $product = makeProduct(1);
    $productRepo->products[] = $product;

    $category1 = makeCategory(10);
    $category2 = makeCategory(20);
    $categoryRepo->categories[] = $category1;
    $categoryRepo->categories[] = $category2;

    $connection->nextQueryResult = [
        ['category_id' => '10'],
        ['category_id' => '20'],
    ];

    $service = new CategoryAssignmentService(
        $productRepo,
        $categoryRepo,
        $connection,
        $connection,
    );

    $result = $service->getCategoriesForProduct(1);

    expect($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(Category::class)
        ->and($result[1])->toBeInstanceOf(Category::class);
});

it('throws ProductNotFoundException from getCategoriesForProduct when the product does not exist', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $connection = new FakeConnection();

    $service = new CategoryAssignmentService(
        $productRepo,
        $categoryRepo,
        $connection,
        $connection,
    );

    expect(fn () => $service->getCategoriesForProduct(99))->toThrow(ProductNotFoundException::class);
});

it('returns the products for a category via getProductsInCategory', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $connection = new FakeConnection();

    $category = makeCategory(2);
    $categoryRepo->categories[] = $category;

    $product1 = makeProduct(10);
    $product2 = makeProduct(20);
    $productRepo->products[] = $product1;
    $productRepo->products[] = $product2;

    $connection->nextQueryResult = [
        ['product_id' => '10'],
        ['product_id' => '20'],
    ];

    $service = new CategoryAssignmentService(
        $productRepo,
        $categoryRepo,
        $connection,
        $connection,
    );

    $result = $service->getProductsInCategory(2);

    expect($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(Product::class)
        ->and($result[1])->toBeInstanceOf(Product::class);
});

it('throws CategoryNotFoundException from getProductsInCategory when the category does not exist', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $connection = new FakeConnection();

    $service = new CategoryAssignmentService(
        $productRepo,
        $categoryRepo,
        $connection,
        $connection,
    );

    expect(fn () => $service->getProductsInCategory(99))->toThrow(CategoryNotFoundException::class);
});

it('works without an event dispatcher by skipping dispatch calls silently', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $connection = new FakeConnection();

    $product = makeProduct(1);
    $category = makeCategory(2);
    $productRepo->products[] = $product;
    $categoryRepo->categories[] = $category;
    $connection->nextQueryResult = [];

    // No dispatcher — null
    $service = new CategoryAssignmentService(
        $productRepo,
        $categoryRepo,
        $connection,
        $connection,
    );

    // Should not throw
    $service->assign(1, 2);

    $connection->executeReturnValue = 1;
    $service->unassign(1, 2);

    expect(true)->toBeTrue(); // No exception thrown
});
