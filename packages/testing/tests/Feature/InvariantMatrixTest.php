<?php

declare(strict_types=1);

namespace Markommerce\Testing\Tests\Feature;

use Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface;
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

// ─── Dataset ─────────────────────────────────────────────────────────────────

function invariantMatrixVendorDir(): string
{
    return dirname(__DIR__, 4) . '/vendor';
}

/**
 * Build the catalog-only preset without any scope axes.
 *
 * Equivalent to StoreProfile::simple() — explicit pgsql driver included.
 */
function matrixSimpleProfile(string $vendorDir): StoreProfile
{
    return StoreProfile::simple($vendorDir);
}

/**
 * Build a single-market, two-locale preset including the pgsql driver.
 *
 * Mirrors StoreProfile::singleMarketTwoLocales() but also loads
 * marko/database-pgsql so the container can resolve repository dependencies.
 */
function matrixTwoLocalesProfile(string $vendorDir): StoreProfile
{
    return StoreProfile::of(
        $vendorDir,
        'markommerce/catalog',
        'markommerce/locale',
        'marko/database-pgsql',
    )->withLocales('default', 'en', 'de');
}

/**
 * Build a two-markets, two-locales preset including the pgsql driver.
 *
 * Mirrors StoreProfile::twoMarketsTwoLocales() but also loads
 * marko/database-pgsql so the container can resolve repository dependencies.
 */
function matrixTwoMarketsProfile(string $vendorDir): StoreProfile
{
    return StoreProfile::of(
        $vendorDir,
        'markommerce/catalog',
        'markommerce/market',
        'markommerce/locale',
        'markommerce/catalog-market',
        'markommerce/catalog-price-index-market',
        'marko/database-pgsql',
    )->withMarkets('us', 'eu')
     ->withLocale('us', 'en')
     ->withLocale('eu', 'de');
}

dataset('storeProfiles', static function (): array {
    $vendorDir = invariantMatrixVendorDir();

    return [
        'simple'      => [matrixSimpleProfile($vendorDir)],
        'two-locales' => [matrixTwoLocalesProfile($vendorDir)],
        'two-markets' => [matrixTwoMarketsProfile($vendorDir)],
    ];
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function invariantMatrixMakeOffsetOptions(CategorySortOrderInterface $sortOrder, int $size = 100): ResolvedPaginationOptions
{
    return new ResolvedPaginationOptions(
        sortOrder: $sortOrder,
        size: $size,
        page: 1,
        presentation: PaginationPresentation::Numbered,
        strategyKind: PaginationStrategyKind::Offset,
        countMode: CountMode::Exact,
    );
}

function invariantMatrixSetPosition(BootedStore $store, int $productId, int $categoryId, int $position): void
{
    /** @var ProductCategoryAssignmentRepositoryInterface $repo */
    $repo = $store->get(ProductCategoryAssignmentRepositoryInterface::class);

    $assignment = $repo->findByProductAndCategory($productId, $categoryId);

    assert($assignment !== null, 'Assignment must exist before setting position');

    $assignment->position = $position;
    $repo->save($assignment);
}

function invariantMatrixHasPriceIndex(BootedStore $store): bool
{
    return $store->container()->has(
        'Markommerce\\CatalogPriceIndex\\Contracts\\ProductPriceIndexRepositoryInterface',
    );
}

// ─── Matrix tests (all three profiles) ───────────────────────────────────────

it(
    'lists products in position order by default in every profile',
    function (StoreProfile $profile): void {
        TestConnection::skipIfUnavailable();

        $testCase = new IntegrationTestCase($profile);
        $testCase->setUpIntegration();

        try {
            $store = $testCase->store;

            /** @var CategorySortOrderRegistry $registry */
            $registry = $store->get(CategorySortOrderRegistry::class);

            $positionOrder = $registry->get('position');
            assert($positionOrder !== null, 'position sort order must be registered in every profile');

            $category = CategoryFactory::new($store)->create();

            $productC = ProductFactory::new($store)->inCategory($category)->create();
            $productA = ProductFactory::new($store)->inCategory($category)->create();
            $productB = ProductFactory::new($store)->inCategory($category)->create();

            invariantMatrixSetPosition($store, (int) $productC->id, (int) $category->id, 30);
            invariantMatrixSetPosition($store, (int) $productA->id, (int) $category->id, 10);
            invariantMatrixSetPosition($store, (int) $productB->id, (int) $category->id, 20);

            /** @var CategoryAssignmentService $service */
            $service = $store->get(CategoryAssignmentService::class);

            $options = invariantMatrixMakeOffsetOptions($positionOrder);
            $page    = $service->paginatedProductsInCategory((int) $category->id, $options);
            $items   = $page->items->toArray();

            expect($items)->toHaveCount(3)
                ->and($items[0]->id)->toBe($productA->id)  // position 10
                ->and($items[1]->id)->toBe($productB->id)  // position 20
                ->and($items[2]->id)->toBe($productC->id); // position 30
        } finally {
            $testCase->tearDownIntegration();
            $testCase->tearDownClass();
        }
    },
)
    ->with('storeProfiles')
    ->group('integration-destructive');

it(
    'renders a category listing without error in every profile',
    function (StoreProfile $profile): void {
        TestConnection::skipIfUnavailable();

        $testCase = new IntegrationTestCase($profile);
        $testCase->setUpIntegration();

        try {
            $store = $testCase->store;

            /** @var CategorySortOrderRegistry $registry */
            $registry = $store->get(CategorySortOrderRegistry::class);

            $positionOrder = $registry->get('position');
            assert($positionOrder !== null, 'position sort order must be registered in every profile');

            $category = CategoryFactory::new($store)->create();

            ProductFactory::new($store)->inCategory($category)->create();
            ProductFactory::new($store)->inCategory($category)->create();

            /** @var CategoryAssignmentService $service */
            $service = $store->get(CategoryAssignmentService::class);

            $options = invariantMatrixMakeOffsetOptions($positionOrder);

            // Must not throw — any profile boots and lists products without error
            $page = $service->paginatedProductsInCategory((int) $category->id, $options);

            expect($page->items->toArray())->toHaveCount(2);
        } finally {
            $testCase->tearDownIntegration();
            $testCase->tearDownClass();
        }
    },
)
    ->with('storeProfiles')
    ->group('integration-destructive');

it(
    'registers the indexed price sort orders only when the price index module is present',
    function (StoreProfile $profile): void {
        TestConnection::skipIfUnavailable();

        $testCase = new IntegrationTestCase($profile);
        $testCase->setUpIntegration();

        try {
            $store = $testCase->store;

            /** @var CategorySortOrderRegistry $registry */
            $registry = $store->get(CategorySortOrderRegistry::class);

            $hasPriceIndex = invariantMatrixHasPriceIndex($store);

            if ($hasPriceIndex) {
                // Profiles with price-index must expose both price sort orders
                expect($registry->has('price_asc'))->toBeTrue()
                    ->and($registry->has('price_desc'))->toBeTrue();
            } else {
                // Profiles without price-index must NOT expose price sort orders
                expect($registry->has('price_asc'))->toBeFalse()
                    ->and($registry->has('price_desc'))->toBeFalse();
            }

            // The position sort order is always present regardless of profile
            expect($registry->has('position'))->toBeTrue();
        } finally {
            $testCase->tearDownIntegration();
            $testCase->tearDownClass();
        }
    },
)
    ->with('storeProfiles')
    ->group('integration-destructive');

// ─── Profile-specific tests ───────────────────────────────────────────────────

it('orders by indexed price ascending in profiles that include the price index', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = matrixTwoMarketsProfile(invariantMatrixVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategorySortOrderRegistry $registry */
        $registry = $store->get(CategorySortOrderRegistry::class);

        $priceAscOrder = $registry->get('price_asc');
        assert($priceAscOrder !== null, 'price_asc must be registered in the two-markets profile');

        $category = CategoryFactory::new($store)->create();

        // Cheap product has lower indexed price
        $cheapProduct     = ProductFactory::new($store)
            ->inCategory($category)
            ->withIndexedPrice('5.99')
            ->create();

        $expensiveProduct = ProductFactory::new($store)
            ->inCategory($category)
            ->withIndexedPrice('99.99')
            ->create();

        /** @var CategoryAssignmentService $service */
        $service = $store->get(CategoryAssignmentService::class);

        $options = invariantMatrixMakeOffsetOptions($priceAscOrder);
        $page    = $service->paginatedProductsInCategory((int) $category->id, $options);
        $items   = $page->items->toArray();

        expect($items)->toHaveCount(2)
            ->and($items[0]->id)->toBe($cheapProduct->id)      // 5.99 — first ascending
            ->and($items[1]->id)->toBe($expensiveProduct->id); // 99.99 — second
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('reflects the active market price override in the two-markets profile', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = matrixTwoMarketsProfile(invariantMatrixVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategorySortOrderRegistry $registry */
        $registry = $store->get(CategorySortOrderRegistry::class);

        $priceAscOrder = $registry->get('price_asc');
        assert($priceAscOrder !== null, 'price_asc must be registered in the two-markets profile');

        $category = CategoryFactory::new($store)->create();

        // Product A: base 10.00, US override 5.00, EU override 100.00
        // Product B: base 20.00, US override 80.00, EU override 1.00
        //
        // US ascending:  A ($5)  → B ($80)
        // EU ascending:  B ($1)  → A ($100)

        $productA = ProductFactory::new($store)->inCategory($category)->withIndexedPrice('10.00')->create();
        $productB = ProductFactory::new($store)->inCategory($category)->withIndexedPrice('20.00')->create();

        $priceIndexInterface = 'Markommerce\\CatalogPriceIndex\\Contracts\\ProductPriceIndexRepositoryInterface';

        /** @var ProductPriceIndexRepositoryInterface $indexRepo */
        $indexRepo = $store->get($priceIndexInterface);

        $entryA = $indexRepo->findByProductId((int) $productA->id);
        assert($entryA !== null, 'Price index entry for product A must exist');

        $entryB = $indexRepo->findByProductId((int) $productB->id);
        assert($entryB !== null, 'Price index entry for product B must exist');

        $entryA->setOverride('market:us', 'amount', '5.0000');
        $entryA->setOverride('market:eu', 'amount', '100.0000');
        $indexRepo->upsertMany([$entryA]);

        $entryB->setOverride('market:us', 'amount', '80.0000');
        $entryB->setOverride('market:eu', 'amount', '1.0000');
        $indexRepo->upsertMany([$entryB]);

        /** @var CategoryAssignmentService $service */
        $service = $store->get(CategoryAssignmentService::class);

        $options = invariantMatrixMakeOffsetOptions($priceAscOrder);

        // In US scope: A (5.00) < B (80.00) → A first
        $store->inScope(
            market: 'us',
            locale: null,
            fn: function () use ($service, $category, $productA, $productB, $options): void {
                $page  = $service->paginatedProductsInCategory((int) $category->id, $options);
                $items = $page->items->toArray();
    
                expect($items)->toHaveCount(2)
                    ->and($items[0]->id)->toBe($productA->id)  // $5 US price
                ->and($items[1]->id)->toBe($productB->id); // $80 US price
        }
        );

        // In EU scope: B (1.00) < A (100.00) → B first
        $store->inScope(
            market: 'eu',
            locale: null,
            fn: function () use ($service, $category, $productA, $productB, $options): void {
                $page  = $service->paginatedProductsInCategory((int) $category->id, $options);
                $items = $page->items->toArray();
    
                expect($items)->toHaveCount(2)
                    ->and($items[0]->id)->toBe($productB->id)  // $1 EU price
                ->and($items[1]->id)->toBe($productA->id); // $100 EU price
        }
        );
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('does not expose price sort orders in the simple profile', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = matrixSimpleProfile(invariantMatrixVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategorySortOrderRegistry $registry */
        $registry = $store->get(CategorySortOrderRegistry::class);

        expect($registry->has('price_asc'))->toBeFalse()
            ->and($registry->has('price_desc'))->toBeFalse()
            ->and($registry->has('position'))->toBeTrue();
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
