<?php

declare(strict_types=1);

use Marko\Database\Repository\RepositoryInterface;
use Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Entity\ProductCategoryAssignment;
use Markommerce\Catalog\Tests\Support\FakeProductCategoryAssignmentRepository;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;

it('ProductRepositoryInterface extends the marko RepositoryInterface', function (): void {
    $reflection = new ReflectionClass(ProductRepositoryInterface::class);

    expect($reflection->isInterface())->toBeTrue();
    expect($reflection->implementsInterface(RepositoryInterface::class))->toBeTrue();
});

it('ProductRepositoryInterface declares a findBySku method', function (): void {
    $reflection = new ReflectionClass(ProductRepositoryInterface::class);

    expect($reflection->hasMethod('findBySku'))->toBeTrue();

    $method = $reflection->getMethod('findBySku');
    $params = $method->getParameters();

    expect($params)->toHaveCount(1);
    expect($params[0]->getName())->toBe('sku');
    expect((string) $params[0]->getType())->toBe('string');
});

it('FakeProductRepository stores and finds a product by id', function (): void {
    $repository = new FakeProductRepository();
    $product = new Product();
    $product->sku = 'SKU-001';
    $product->name = 'Test Product';

    $repository->save($product);

    expect($product->id)->not->toBeNull();

    $found = $repository->find($product->id);

    expect($found)->not->toBeNull();
    expect($found->id)->toBe($product->id);
    expect($found->sku)->toBe('SKU-001');
});

it('FakeProductRepository finds a product by sku and returns null for an unknown sku', function (): void {
    $repository = new FakeProductRepository();
    $product = new Product();
    $product->sku = 'SKU-123';
    $product->name = 'Test Product';

    $repository->save($product);

    $found = $repository->findBySku('SKU-123');

    expect($found)->not->toBeNull();
    expect($found->sku)->toBe('SKU-123');

    $notFound = $repository->findBySku('UNKNOWN-SKU');

    expect($notFound)->toBeNull();
});

it(
    'ProductCategoryAssignmentRepositoryInterface declares findByCategory and findByProductAndCategory methods',
    function (): void {
        $reflection = new ReflectionClass(ProductCategoryAssignmentRepositoryInterface::class);
    
        expect($reflection->isInterface())->toBeTrue();
        expect($reflection->implementsInterface(RepositoryInterface::class))->toBeTrue();
        expect($reflection->hasMethod('findByCategory'))->toBeTrue();
        expect($reflection->hasMethod('findByProductAndCategory'))->toBeTrue();
    
        $findByCategory = $reflection->getMethod('findByCategory');
        $categoryParams = $findByCategory->getParameters();
        expect($categoryParams)->toHaveCount(1);
        expect($categoryParams[0]->getName())->toBe('categoryId');
        expect((string) $categoryParams[0]->getType())->toBe('int');
    
        $findByProductAndCategory = $reflection->getMethod('findByProductAndCategory');
        $assignmentParams = $findByProductAndCategory->getParameters();
        expect($assignmentParams)->toHaveCount(2);
        expect($assignmentParams[0]->getName())->toBe('productId');
        expect((string) $assignmentParams[0]->getType())->toBe('int');
        expect($assignmentParams[1]->getName())->toBe('categoryId');
        expect((string) $assignmentParams[1]->getType())->toBe('int');
    }
);

it('FakeProductCategoryAssignmentRepository returns only assignments matching a given category', function (): void {
    $repository = new FakeProductCategoryAssignmentRepository();

    $assignment1 = new ProductCategoryAssignment();
    $assignment1->productId = 1;
    $assignment1->categoryId = 10;

    $assignment2 = new ProductCategoryAssignment();
    $assignment2->productId = 2;
    $assignment2->categoryId = 10;

    $assignment3 = new ProductCategoryAssignment();
    $assignment3->productId = 1;
    $assignment3->categoryId = 20;

    $repository->save($assignment1);
    $repository->save($assignment2);
    $repository->save($assignment3);

    $results = $repository->findByCategory(10);

    expect($results)->toHaveCount(2);
    expect(array_any($results, fn ($a) => $a->productId === 1 && $a->categoryId === 10))->toBeTrue();
    expect(array_any($results, fn ($a) => $a->productId === 2 && $a->categoryId === 10))->toBeTrue();

    $resultsForCategory20 = $repository->findByCategory(20);
    expect($resultsForCategory20)->toHaveCount(1);
    expect($resultsForCategory20[0]->productId)->toBe(1);
});
