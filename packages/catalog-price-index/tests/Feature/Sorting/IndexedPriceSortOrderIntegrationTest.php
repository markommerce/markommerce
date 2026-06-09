<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndex\Tests\Feature\Sorting;

use Markommerce\Catalog\Pagination\CountMode;
use Markommerce\Catalog\Pagination\PaginationPresentation;
use Markommerce\Catalog\Pagination\PaginationStrategyKind;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Sorting\CategorySortOrderInterface;
use Markommerce\Catalog\Sorting\CategorySortOrderRegistry;
use Markommerce\Catalog\Tests\Support\CategoryFactory;
use Markommerce\Catalog\Tests\Support\ProductFactory;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function indexedPriceSortVendorDir(): string
{
    return dirname(__DIR__, 5) . '/vendor';
}

function indexedPriceProfile(string $vendorDir): StoreProfile
{
    return StoreProfile::of($vendorDir, 'markommerce/catalog-price-index', 'marko/database-pgsql');
}

function indexedPriceMakeOffsetOptions(CategorySortOrderInterface $sortOrder): ResolvedPaginationOptions
{
    return new ResolvedPaginationOptions(
        sortOrder: $sortOrder,
        size: 100,
        page: 1,
        presentation: PaginationPresentation::Numbered,
        strategyKind: PaginationStrategyKind::Offset,
        countMode: CountMode::Exact,
    );
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('it places non-indexed products last in ascending order', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = indexedPriceProfile(indexedPriceSortVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategorySortOrderRegistry $registry */
        $registry = $store->get(CategorySortOrderRegistry::class);

        $ascOrder = $registry->get('price_asc');
        assert($ascOrder !== null, 'price_asc sort order must be registered in the price-index profile');

        $category    = CategoryFactory::new($store)->create();
        $categoryId  = (int) $category->id;

        $cheap     = ProductFactory::new($store)->inCategory($category)->withIndexedPrice('9.99')->create();
        $expensive = ProductFactory::new($store)->inCategory($category)->withIndexedPrice('49.99')->create();
        $noIndex   = ProductFactory::new($store)->inCategory($category)->create();

        $cheapId     = (int) $cheap->id;
        $expensiveId = (int) $expensive->id;
        $noIndexId   = (int) $noIndex->id;

        /** @var CategoryAssignmentService $service */
        $service  = $store->get(CategoryAssignmentService::class);
        $options  = indexedPriceMakeOffsetOptions($ascOrder);
        $page     = $service->paginatedProductsInCategory($categoryId, $options);
        $products = $page->items->toArray();

        expect($products)->toHaveCount(3);

        // Indexed products come first in ascending price order
        expect($products[0]->id)->toBe($cheapId);
        expect($products[1]->id)->toBe($expensiveId);
        // NULL-priced product comes last
        expect($products[2]->id)->toBe($noIndexId);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it places non-indexed products last in descending order', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = indexedPriceProfile(indexedPriceSortVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategorySortOrderRegistry $registry */
        $registry = $store->get(CategorySortOrderRegistry::class);

        $descOrder = $registry->get('price_desc');
        assert($descOrder !== null, 'price_desc sort order must be registered in the price-index profile');

        $category    = CategoryFactory::new($store)->create();
        $categoryId  = (int) $category->id;

        $cheap     = ProductFactory::new($store)->inCategory($category)->withIndexedPrice('9.99')->create();
        $expensive = ProductFactory::new($store)->inCategory($category)->withIndexedPrice('49.99')->create();
        $noIndex   = ProductFactory::new($store)->inCategory($category)->create();

        $cheapId     = (int) $cheap->id;
        $expensiveId = (int) $expensive->id;
        $noIndexId   = (int) $noIndex->id;

        /** @var CategoryAssignmentService $service */
        $service  = $store->get(CategoryAssignmentService::class);
        $options  = indexedPriceMakeOffsetOptions($descOrder);
        $page     = $service->paginatedProductsInCategory($categoryId, $options);
        $products = $page->items->toArray();

        expect($products)->toHaveCount(3);

        // Indexed products come first in descending price order
        expect($products[0]->id)->toBe($expensiveId);
        expect($products[1]->id)->toBe($cheapId);
        // NULL-priced product comes last
        expect($products[2]->id)->toBe($noIndexId);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
