<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndexMarket\Tests\Feature\Sorting;

use Markommerce\Catalog\Pagination\CountMode;
use Markommerce\Catalog\Pagination\PaginationPresentation;
use Markommerce\Catalog\Pagination\PaginationStrategyKind;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Sorting\CategorySortOrderInterface;
use Markommerce\Catalog\Sorting\CategorySortOrderRegistry;
use Markommerce\Catalog\Tests\Support\CategoryFactory;
use Markommerce\Catalog\Tests\Support\ProductFactory;
use Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface;
use Markommerce\Scope\Exceptions\ScopeStorageException;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\BootedStore;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function scopedPriceSortVendorDir(): string
{
    return dirname(__DIR__, 5) . '/vendor';
}

function scopedPriceSortMakeOffsetOptions(CategorySortOrderInterface $sortOrder): ResolvedPaginationOptions
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

/**
 * Set a market-scoped price override on an already-indexed product.
 *
 * @throws ScopeStorageException
 */
function scopedPriceSortSetMarketOverride(BootedStore $store, int $productId, string $market, string $amount): void
{
    /** @var ProductPriceIndexRepositoryInterface $indexRepo */
    $indexRepo = $store->get(ProductPriceIndexRepositoryInterface::class);

    $entry = $indexRepo->findByProductId($productId);
    assert($entry !== null, "Price index entry for product $productId must exist");

    $entry->setOverride("market:$market", 'amount', $amount);
    $indexRepo->upsertMany([$entry]);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

// Kept: real-SQL LEFT-JOIN guard — verifies that the market-scoped sort expression uses a
// LEFT JOIN so products with no price index row still appear in results. The other four
// cases have been removed: market-override-wins is covered by InvariantMatrixTest
// (`reflects the active market price override in the two-markets profile`); numeric-cast
// is covered by the unit MarketScopedPriceExpressionTest; fallback-to-base is also covered
// by InvariantMatrixTest; nulls-last result-order is retained once at the non-market layer
// in EndToEndPriceSortOrderIntegrationTest.

it('it keeps the price index left join in the prepared query', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::twoMarketsTwoLocales(scopedPriceSortVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategorySortOrderRegistry $registry */
        $registry = $store->get(CategorySortOrderRegistry::class);

        $priceAscOrder = $registry->get('price_asc');
        assert($priceAscOrder !== null, 'price_asc must be registered in the two-markets profile');

        $category   = CategoryFactory::new($store)->create();
        $categoryId = (int) $category->id;

        $indexed  = ProductFactory::new($store)->inCategory($category)->withIndexedPrice('15.00')->create();
        $noIndex  = ProductFactory::new($store)->inCategory($category)->create();

        $indexedId  = (int) $indexed->id;
        $noIndexId  = (int) $noIndex->id;

        scopedPriceSortSetMarketOverride($store, $indexedId, 'us', '15.0000');
        // $noIndex has no price index row — LEFT JOIN should still return this product

        /** @var CategoryAssignmentService $service */
        $service = $store->get(CategoryAssignmentService::class);

        $options = scopedPriceSortMakeOffsetOptions($priceAscOrder);

        $store->inScope(market: 'us', locale: null, fn: function () use ($service, $categoryId, $options): void {
            $page     = $service->paginatedProductsInCategory($categoryId, $options);
            $products = $page->items->toArray();

            // Both products should appear (LEFT JOIN, not INNER JOIN)
            expect($products)->toHaveCount(2);
        });
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
