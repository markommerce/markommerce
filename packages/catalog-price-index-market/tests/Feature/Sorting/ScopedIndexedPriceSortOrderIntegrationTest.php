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
 * @throws \Markommerce\Scope\Exceptions\ScopeStorageException
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

it('it orders by the active market override amount when present', function (): void {
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

        // Base price is 49.99 and 9.99, but market:us prices are 5.00 and 100.00
        // so ascending order by market:us price should be: marketCheap ($5), marketExpensive ($100)
        $marketCheap     = ProductFactory::new($store)->inCategory($category)->withIndexedPrice('49.99')->create();
        $marketExpensive = ProductFactory::new($store)->inCategory($category)->withIndexedPrice('9.99')->create();

        $marketCheapId     = (int) $marketCheap->id;
        $marketExpensiveId = (int) $marketExpensive->id;

        // Market cheap product has a higher base price but lower market:us override
        scopedPriceSortSetMarketOverride($store, $marketCheapId, 'us', '5.0000');
        scopedPriceSortSetMarketOverride($store, $marketExpensiveId, 'us', '100.0000');

        /** @var CategoryAssignmentService $service */
        $service = $store->get(CategoryAssignmentService::class);

        $options = scopedPriceSortMakeOffsetOptions($priceAscOrder);

        $store->inScope(market: 'us', locale: null, fn: function () use ($service, $categoryId, $marketCheapId, $marketExpensiveId, $options): void {
            $page     = $service->paginatedProductsInCategory($categoryId, $options);
            $products = $page->items->toArray();

            expect($products)->toHaveCount(2);
            // Market cheap ($5 us override) should come first in ascending order
            expect($products[0]->id)->toBe($marketCheapId);
            expect($products[1]->id)->toBe($marketExpensiveId);
        });
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it falls back to the base amount when the active market has no override', function (): void {
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

        // No market:us override — only base amounts
        $cheap     = ProductFactory::new($store)->inCategory($category)->withIndexedPrice('10.00')->create();
        $expensive = ProductFactory::new($store)->inCategory($category)->withIndexedPrice('50.00')->create();

        $cheapId     = (int) $cheap->id;
        $expensiveId = (int) $expensive->id;

        /** @var CategoryAssignmentService $service */
        $service = $store->get(CategoryAssignmentService::class);

        $options = scopedPriceSortMakeOffsetOptions($priceAscOrder);

        $store->inScope(market: 'us', locale: null, fn: function () use ($service, $categoryId, $cheapId, $expensiveId, $options): void {
            $page     = $service->paginatedProductsInCategory($categoryId, $options);
            $products = $page->items->toArray();

            expect($products)->toHaveCount(2);
            // Without market override, COALESCE falls back to base amount
            expect($products[0]->id)->toBe($cheapId);
            expect($products[1]->id)->toBe($expensiveId);
        });
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it still places products with no price last', function (): void {
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

        $priced  = ProductFactory::new($store)->inCategory($category)->withIndexedPrice('20.00')->create();
        $noPrice = ProductFactory::new($store)->inCategory($category)->create();

        $pricedId  = (int) $priced->id;
        $noPriceId = (int) $noPrice->id;

        scopedPriceSortSetMarketOverride($store, $pricedId, 'us', '20.0000');

        /** @var CategoryAssignmentService $service */
        $service = $store->get(CategoryAssignmentService::class);

        $options = scopedPriceSortMakeOffsetOptions($priceAscOrder);

        $store->inScope(market: 'us', locale: null, fn: function () use ($service, $categoryId, $pricedId, $noPriceId, $options): void {
            $page     = $service->paginatedProductsInCategory($categoryId, $options);
            $products = $page->items->toArray();

            expect($products)->toHaveCount(2);
            expect($products[0]->id)->toBe($pricedId);
            expect($products[1]->id)->toBe($noPriceId);
        });
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it casts the json override amount to numeric so 100 sorts after 9', function (): void {
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

        // These amounts would sort incorrectly as text: "100" < "9" lexicographically
        $nine    = ProductFactory::new($store)->inCategory($category)->withIndexedPrice('9.00')->create();
        $hundred = ProductFactory::new($store)->inCategory($category)->withIndexedPrice('100.00')->create();

        $nineId    = (int) $nine->id;
        $hundredId = (int) $hundred->id;

        scopedPriceSortSetMarketOverride($store, $nineId, 'us', '9.0000');
        scopedPriceSortSetMarketOverride($store, $hundredId, 'us', '100.0000');

        /** @var CategoryAssignmentService $service */
        $service = $store->get(CategoryAssignmentService::class);

        $options = scopedPriceSortMakeOffsetOptions($priceAscOrder);

        $store->inScope(market: 'us', locale: null, fn: function () use ($service, $categoryId, $nineId, $hundredId, $options): void {
            $page     = $service->paginatedProductsInCategory($categoryId, $options);
            $products = $page->items->toArray();

            expect($products)->toHaveCount(2);
            // With ::numeric cast, 9 < 100 (not "100" < "9" as text comparison)
            expect($products[0]->id)->toBe($nineId);
            expect($products[1]->id)->toBe($hundredId);
        });
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

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
