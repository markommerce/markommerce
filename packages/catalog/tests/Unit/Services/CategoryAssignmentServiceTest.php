<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Exceptions\CategoryNotFoundException;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeProductCategoryAssignmentRepository;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;

it('assigns a product to a category creating an assignment row', function (): void {
    $productRepository = new FakeProductRepository();
    $categoryRepository = new FakeCategoryRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $product = new Product();
    $product->sku = 'PROD-001';
    $product->name = 'Test Product';
    $productRepository->save($product);

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $service = new CategoryAssignmentService(
        productRepository: $productRepository,
        categoryRepository: $categoryRepository,
        productCategoryAssignmentRepository: $assignmentRepository,
    );

    $service->assign($product->id, $category->id);

    expect($assignmentRepository->assignments)->toHaveCount(1);
    $assignment = array_values($assignmentRepository->assignments)[0];
    expect($assignment->productId)->toBe($product->id)
        ->and($assignment->categoryId)->toBe($category->id);
});

it('does not create a duplicate assignment when the product is already in the category', function (): void {
    $productRepository = new FakeProductRepository();
    $categoryRepository = new FakeCategoryRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $product = new Product();
    $product->sku = 'PROD-001';
    $product->name = 'Test Product';
    $productRepository->save($product);

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $service = new CategoryAssignmentService(
        productRepository: $productRepository,
        categoryRepository: $categoryRepository,
        productCategoryAssignmentRepository: $assignmentRepository,
    );

    $service->assign($product->id, $category->id);
    $service->assign($product->id, $category->id);

    expect($assignmentRepository->assignments)->toHaveCount(1);
});

it('throws CategoryNotFoundException when assigning to a category that does not exist', function (): void {
    $productRepository = new FakeProductRepository();
    $categoryRepository = new FakeCategoryRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $product = new Product();
    $product->sku = 'PROD-001';
    $product->name = 'Test Product';
    $productRepository->save($product);

    $service = new CategoryAssignmentService(
        productRepository: $productRepository,
        categoryRepository: $categoryRepository,
        productCategoryAssignmentRepository: $assignmentRepository,
    );

    expect(fn () => $service->assign($product->id, 999))
        ->toThrow(CategoryNotFoundException::class);
});

it('detaches a product from a category removing the assignment row', function (): void {
    $productRepository = new FakeProductRepository();
    $categoryRepository = new FakeCategoryRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $product = new Product();
    $product->sku = 'PROD-001';
    $product->name = 'Test Product';
    $productRepository->save($product);

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $service = new CategoryAssignmentService(
        productRepository: $productRepository,
        categoryRepository: $categoryRepository,
        productCategoryAssignmentRepository: $assignmentRepository,
    );

    $service->assign($product->id, $category->id);
    expect($assignmentRepository->assignments)->toHaveCount(1);

    $service->detach($product->id, $category->id);
    expect($assignmentRepository->assignments)->toHaveCount(0);
});

it('does nothing when detaching a product that is not assigned to the category', function (): void {
    $productRepository = new FakeProductRepository();
    $categoryRepository = new FakeCategoryRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $service = new CategoryAssignmentService(
        productRepository: $productRepository,
        categoryRepository: $categoryRepository,
        productCategoryAssignmentRepository: $assignmentRepository,
    );

    // Should not throw any exception
    $service->detach(1, 1);

    expect($assignmentRepository->assignments)->toHaveCount(0);
});

it('lists every product assigned to a category', function (): void {
    $productRepository = new FakeProductRepository();
    $categoryRepository = new FakeCategoryRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $product1 = new Product();
    $product1->sku = 'PROD-001';
    $product1->name = 'Product One';
    $productRepository->save($product1);

    $product2 = new Product();
    $product2->sku = 'PROD-002';
    $product2->name = 'Product Two';
    $productRepository->save($product2);

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $service = new CategoryAssignmentService(
        productRepository: $productRepository,
        categoryRepository: $categoryRepository,
        productCategoryAssignmentRepository: $assignmentRepository,
    );

    $service->assign($product1->id, $category->id);
    $service->assign($product2->id, $category->id);

    $products = $service->productsInCategory($category->id);

    expect($products)->toHaveCount(2)
        ->and($products[0])->toBeInstanceOf(Product::class)
        ->and($products[1])->toBeInstanceOf(Product::class);

    $skus = array_map(fn (Product $p) => $p->sku, $products);
    expect($skus)->toContain('PROD-001')->toContain('PROD-002');
});

it('skips an assignment whose product no longer exists when listing a category', function (): void {
    $productRepository = new FakeProductRepository();
    $categoryRepository = new FakeCategoryRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $product = new Product();
    $product->sku = 'PROD-001';
    $product->name = 'Test Product';
    $productRepository->save($product);

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $service = new CategoryAssignmentService(
        productRepository: $productRepository,
        categoryRepository: $categoryRepository,
        productCategoryAssignmentRepository: $assignmentRepository,
    );

    $service->assign($product->id, $category->id);

    // Simulate orphaned assignment by removing product from repository
    $productRepository->delete($product);

    $products = $service->productsInCategory($category->id);

    expect($products)->toHaveCount(0);
});

it('throws CategoryNotFoundException when listing products for a category that does not exist', function (): void {
    $productRepository = new FakeProductRepository();
    $categoryRepository = new FakeCategoryRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $service = new CategoryAssignmentService(
        productRepository: $productRepository,
        categoryRepository: $categoryRepository,
        productCategoryAssignmentRepository: $assignmentRepository,
    );

    expect(fn () => $service->productsInCategory(999))
        ->toThrow(CategoryNotFoundException::class);
});
