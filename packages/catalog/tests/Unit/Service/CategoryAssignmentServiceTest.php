<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Unit\Service;

use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Event\ProductAssignedToCategory;
use Markommerce\Catalog\Event\ProductRemovedFromCategory;
use Markommerce\Catalog\Exception\CategoryNotFoundException;
use Markommerce\Catalog\Exception\ProductNotFoundException;
use Markommerce\Catalog\Repository\ProductCategoryRepositoryInterface;
use Markommerce\Catalog\Service\CategoryAssignmentService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeProductCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;
use Markommerce\Catalog\Tests\Support\RecordingEventDispatcher;

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

it('constructs CategoryAssignmentService with ProductCategoryRepositoryInterface instead of ConnectionInterface and TransactionInterface', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $pivotRepo = new FakeProductCategoryRepository();

    $service = new CategoryAssignmentService($productRepo, $categoryRepo, $pivotRepo);

    expect($service)->toBeInstanceOf(CategoryAssignmentService::class);

    $reflection = new \ReflectionClass($service);
    $constructor = $reflection->getConstructor();
    assert($constructor !== null);

    $paramTypes = array_map(
        fn (\ReflectionParameter $p): ?string => $p->getType() instanceof \ReflectionNamedType
            ? $p->getType()->getName()
            : null,
        $constructor->getParameters(),
    );

    expect($paramTypes)->toContain(ProductCategoryRepositoryInterface::class);
    expect($paramTypes)->not->toContain('Marko\\Database\\Connection\\ConnectionInterface');
    expect($paramTypes)->not->toContain('Marko\\Database\\Connection\\TransactionInterface');
});

it('assigns a product to a category by delegating to the pivot repository and dispatches ProductAssignedToCategory on a fresh insert', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $pivotRepo = new FakeProductCategoryRepository();
    $dispatcher = new RecordingEventDispatcher();

    $productRepo->products[] = makeProduct(1);
    $categoryRepo->categories[] = makeCategory(2);

    $service = new CategoryAssignmentService($productRepo, $categoryRepo, $pivotRepo, $dispatcher);
    $service->assign(1, 2);

    expect($pivotRepo->assignments)->toContain([1, 2]);
    expect($dispatcher->events)->toHaveCount(1);
    expect($dispatcher->events[0])->toBeInstanceOf(ProductAssignedToCategory::class);
    assert($dispatcher->events[0] instanceof ProductAssignedToCategory);
    expect($dispatcher->events[0]->productId)->toBe(1);
    expect($dispatcher->events[0]->categoryId)->toBe(2);
});

it('does not redispatch ProductAssignedToCategory when the pivot repository reports the assignment already exists', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $pivotRepo = new FakeProductCategoryRepository();
    $dispatcher = new RecordingEventDispatcher();

    $productRepo->products[] = makeProduct(1);
    $categoryRepo->categories[] = makeCategory(2);

    // Pre-seed the assignment so the repo will return false (already exists)
    $pivotRepo->assignments[] = [1, 2];

    $service = new CategoryAssignmentService($productRepo, $categoryRepo, $pivotRepo, $dispatcher);
    $service->assign(1, 2);

    expect($dispatcher->events)->toBeEmpty();
});

it('throws ProductNotFoundException from assign when the product does not exist before consulting the category', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $pivotRepo = new FakeProductCategoryRepository();

    $categoryRepo->categories[] = makeCategory(2);

    $service = new CategoryAssignmentService($productRepo, $categoryRepo, $pivotRepo);

    expect(fn () => $service->assign(99, 2))->toThrow(ProductNotFoundException::class);
});

it('throws CategoryNotFoundException from assign when the product exists but the category does not', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $pivotRepo = new FakeProductCategoryRepository();

    $productRepo->products[] = makeProduct(1);

    $service = new CategoryAssignmentService($productRepo, $categoryRepo, $pivotRepo);

    expect(fn () => $service->assign(1, 99))->toThrow(CategoryNotFoundException::class);
});

it('unassigns by delegating to the pivot repository and dispatches ProductRemovedFromCategory only when the repository reports a row was removed', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $pivotRepo = new FakeProductCategoryRepository();
    $dispatcher = new RecordingEventDispatcher();

    $pivotRepo->assignments[] = [1, 2];

    $service = new CategoryAssignmentService($productRepo, $categoryRepo, $pivotRepo, $dispatcher);
    $service->unassign(1, 2);

    expect($pivotRepo->assignments)->toBeEmpty();
    expect($dispatcher->events)->toHaveCount(1);
    expect($dispatcher->events[0])->toBeInstanceOf(ProductRemovedFromCategory::class);
    assert($dispatcher->events[0] instanceof ProductRemovedFromCategory);
    expect($dispatcher->events[0]->productId)->toBe(1);
    expect($dispatcher->events[0]->categoryId)->toBe(2);
});

it('is a no-op when unassigning a non-existent assignment and does not dispatch', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $pivotRepo = new FakeProductCategoryRepository();
    $dispatcher = new RecordingEventDispatcher();

    // No assignment pre-seeded; unassign returns false

    $service = new CategoryAssignmentService($productRepo, $categoryRepo, $pivotRepo, $dispatcher);
    $service->unassign(1, 2);

    expect($dispatcher->events)->toBeEmpty();
});

it('returns the categories for a product by calling findCategoryIdsForProduct and hydrating via the category repository', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $pivotRepo = new FakeProductCategoryRepository();

    $productRepo->products[] = makeProduct(1);

    $category10 = makeCategory(10);
    $category20 = makeCategory(20);
    $categoryRepo->categories[] = $category10;
    $categoryRepo->categories[] = $category20;

    $pivotRepo->assignments[] = [1, 10];
    $pivotRepo->assignments[] = [1, 20];

    $service = new CategoryAssignmentService($productRepo, $categoryRepo, $pivotRepo);
    $result = $service->getCategoriesForProduct(1);

    expect($result)->toHaveCount(2);
    expect($result[0])->toBeInstanceOf(Category::class);
    expect($result[1])->toBeInstanceOf(Category::class);
});

it('returns an empty array from getCategoriesForProduct when the product has no assignments without calling the category repository', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $pivotRepo = new FakeProductCategoryRepository();

    $productRepo->products[] = makeProduct(1);
    // No assignments

    $service = new CategoryAssignmentService($productRepo, $categoryRepo, $pivotRepo);
    $result = $service->getCategoriesForProduct(1);

    expect($result)->toBeEmpty();
});

it('throws ProductNotFoundException from getCategoriesForProduct when the product does not exist', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $pivotRepo = new FakeProductCategoryRepository();

    $service = new CategoryAssignmentService($productRepo, $categoryRepo, $pivotRepo);

    expect(fn () => $service->getCategoriesForProduct(99))->toThrow(ProductNotFoundException::class);
});

it('returns the products in a category by calling findProductIdsForCategory and hydrating via the product repository', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $pivotRepo = new FakeProductCategoryRepository();

    $categoryRepo->categories[] = makeCategory(2);

    $product10 = makeProduct(10);
    $product20 = makeProduct(20);
    $productRepo->products[] = $product10;
    $productRepo->products[] = $product20;

    $pivotRepo->assignments[] = [10, 2];
    $pivotRepo->assignments[] = [20, 2];

    $service = new CategoryAssignmentService($productRepo, $categoryRepo, $pivotRepo);
    $result = $service->getProductsInCategory(2);

    expect($result)->toHaveCount(2);
    expect($result[0])->toBeInstanceOf(Product::class);
    expect($result[1])->toBeInstanceOf(Product::class);
});

it('throws CategoryNotFoundException from getProductsInCategory when the category does not exist', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $pivotRepo = new FakeProductCategoryRepository();

    $service = new CategoryAssignmentService($productRepo, $categoryRepo, $pivotRepo);

    expect(fn () => $service->getProductsInCategory(99))->toThrow(CategoryNotFoundException::class);
});

it('works without an event dispatcher by skipping dispatch calls silently', function (): void {
    $productRepo = new FakeProductRepository();
    $categoryRepo = new FakeCategoryRepository();
    $pivotRepo = new FakeProductCategoryRepository();

    $productRepo->products[] = makeProduct(1);
    $categoryRepo->categories[] = makeCategory(2);

    // No dispatcher — null
    $service = new CategoryAssignmentService($productRepo, $categoryRepo, $pivotRepo);

    $service->assign(1, 2);
    $service->unassign(1, 2);

    expect(true)->toBeTrue(); // No exception thrown
});

it('binds ProductCategoryRepositoryInterface to ProductCategoryRepository in catalog module.php', function (): void {
    $module = require dirname(__DIR__, 3) . '/module.php';
    $bindings = $module['bindings'];

    expect($bindings)->toHaveKey(\Markommerce\Catalog\Repository\ProductCategoryRepositoryInterface::class);
    expect($bindings[\Markommerce\Catalog\Repository\ProductCategoryRepositoryInterface::class])
        ->toBe(\Markommerce\Catalog\Repository\ProductCategoryRepository::class);
});
