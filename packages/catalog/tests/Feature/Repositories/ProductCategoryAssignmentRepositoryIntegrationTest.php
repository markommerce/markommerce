<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Feature\Repositories;

use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Entity\ProductCategoryAssignment;
use Markommerce\Catalog\Repositories\CategoryRepository;
use Markommerce\Catalog\Repositories\ProductCategoryAssignmentRepository;
use Markommerce\Catalog\Repositories\ProductRepository;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function prodCatAssignRepoVendorDir(): string
{
    return dirname(__DIR__, 5) . '/vendor';
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('it persists and reads back the assignment position', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::simple(prodCatAssignRepoVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ProductRepository $productRepository */
        $productRepository = $store->get(ProductRepository::class);

        /** @var CategoryRepository $categoryRepository */
        $categoryRepository = $store->get(CategoryRepository::class);

        /** @var ProductCategoryAssignmentRepository $repository */
        $repository = $store->get(ProductCategoryAssignmentRepository::class);

        $product = new Product();
        $product->name = 'PCA Test Product';
        $product->sku = 'PCA-SKU-' . bin2hex(random_bytes(4));
        $productRepository->save($product);

        $category = new Category();
        $category->name = 'PCA Test Category';
        $categoryRepository->save($category);

        $assignment = new ProductCategoryAssignment();
        $assignment->productId = (int) $product->id;
        $assignment->categoryId = (int) $category->id;
        $assignment->position = 5;

        $repository->save($assignment);

        expect($assignment->id)->not->toBeNull();

        $found = $repository->find($assignment->id);

        expect($found)->not->toBeNull()
            ->and($found->id)->toBe($assignment->id)
            ->and($found->position)->toBe(5);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
