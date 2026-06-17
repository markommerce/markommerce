<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndex\Tests\Feature\Sorting;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;
use Markommerce\Catalog\Pagination\PaginationOptionsResolver;
use Markommerce\Catalog\Repositories\CategoryRepository;
use Markommerce\Catalog\Repositories\ProductCategoryAssignmentRepository;
use Markommerce\Catalog\Repositories\ProductRepository;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Sorting\CategorySortOrderRegistry;
use Markommerce\Catalog\Sorting\ColumnSortOrder;
use Markommerce\Catalog\Tests\Support\CategoryFactory;
use Markommerce\Catalog\Tests\Support\ProductFactory;
use Markommerce\CatalogPriceIndex\Sorting\AscendingIndexedPriceSortOrder;
use Markommerce\CatalogPriceIndex\Sorting\DescendingIndexedPriceSortOrder;
use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Criteria\Position\PositionCodec;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Strategy\KeysetPaginationStrategy;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function e2ePriceVendorDir(): string
{
    return dirname(__DIR__, 5) . '/vendor';
}

function e2ePriceProfile(): StoreProfile
{
    return StoreProfile::of(
        e2ePriceVendorDir(),
        'markommerce/catalog-price-index',
        'marko/database-pgsql',
    );
}

function e2ePriceMakeServiceFromConn(ConnectionInterface $conn): CategoryAssignmentService
{
    $metadataFactory      = new EntityMetadataFactory();
    $hydrator             = new EntityHydrator($metadataFactory);
    $queryBuilderFactory  = new PgSqlQueryBuilderFactory($conn);
    $productRepository    = new ProductRepository($conn, $metadataFactory, $hydrator, $queryBuilderFactory);
    $categoryRepository   = new CategoryRepository($conn, $metadataFactory, $hydrator);
    $assignmentRepository = new ProductCategoryAssignmentRepository($conn, $metadataFactory, $hydrator);
    $positionCodec        = new PositionCodec();
    $keysetStrategy       = new KeysetPaginationStrategy($positionCodec);

    return new CategoryAssignmentService(
        productRepository: $productRepository,
        categoryRepository: $categoryRepository,
        productCategoryAssignmentRepository: $assignmentRepository,
        positionCodec: $positionCodec,
        keysetPaginationStrategy: $keysetStrategy,
        connection: $conn,
    );
}

function e2ePriceMakeRegistry(): CategorySortOrderRegistry
{
    $registry = new CategorySortOrderRegistry();

    $registry->register(new ColumnSortOrder(
        key: 'position',
        label: 'Position',
        column: 'catalog_product_category.position',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    ), 0);

    $registry->register(new AscendingIndexedPriceSortOrder(), 10);
    $registry->register(new DescendingIndexedPriceSortOrder(), 20);

    return $registry;
}

/**
 * @param array<string, mixed> $configOverrides
 */
function e2ePriceMakeResolver(
    CategorySortOrderRegistry $registry,
    array $configOverrides = [],
): PaginationOptionsResolver {
    $defaults = [
        'defaultPageSize'  => 3,
        'allowedPageSizes' => [3, 5, 10, 20],
        'maxPageSize'      => 50,
        'strategy'         => 'offset',
        'presentation'     => 'numbered',
        'countMode'        => 'exact',
        'maxPageDepth'     => 100,
        'defaultSort'      => 'position',
        'enabledSorts'     => [],
        'viewAllThreshold' => 0,
        'countCacheTtl'    => 0,
    ];

    $values = array_merge($defaults, $configOverrides);

    $configResolver = new class ($values) implements ConfigResolverInterface
    {
        /** @param array<string, mixed> $values */
        public function __construct(private array $values) {}

        public function resolved(
            string $configClass,
            string $field,
        ): mixed {
            return $this->values[$field] ?? null;
        }
    };

    return new PaginationOptionsResolver($configResolver, $registry);
}

/**
 * Set or update the price index entry for a product.
 */
function e2ePriceSetIndexEntry(ConnectionInterface $conn, int $productId, string $amount): void
{
    $conn->execute(
        "INSERT INTO catalog_product_price_index (product_id, amount, currency_code)
         VALUES (?, ?, 'USD')
         ON CONFLICT (product_id) DO UPDATE SET amount = EXCLUDED.amount",
        [$productId, $amount],
    );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('places non-indexed products last in descending order with NULL prices last', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(e2ePriceProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConnectionInterface $conn */
        $conn = $store->container()->get(ConnectionInterface::class);

        $registry = e2ePriceMakeRegistry();
        $resolver = e2ePriceMakeResolver($registry);
        $service  = e2ePriceMakeServiceFromConn($conn);

        $categoryFactory = CategoryFactory::new($store);
        $productFactory  = ProductFactory::new($store);

        $category  = $categoryFactory->withName('Desc NULLs-Last Category')->create();
        $cheap     = $productFactory->withName('Budget Item')->withSku('E2E-DESC-NULL-CHEAP')->create();
        $expensive = $productFactory->withName('Premium Item')->withSku('E2E-DESC-NULL-PREM')->create();
        $noIndex   = $productFactory->withName('No-Index Item')->withSku('E2E-DESC-NULL-NOIDX')->create();

        foreach ([(int) $cheap->id, (int) $expensive->id, (int) $noIndex->id] as $pid) {
            $conn->execute(
                'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, 0)',
                [$pid, (int) $category->id],
            );
        }

        e2ePriceSetIndexEntry($conn, (int) $cheap->id, '9.99');
        e2ePriceSetIndexEntry($conn, (int) $expensive->id, '49.99');
        // $noIndex has no price index entry — NULL, must come last

        $options  = $resolver->resolve(page: 1, size: 10, sort: 'price_desc');
        $page     = $service->paginatedProductsInCategory((int) $category->id, $options);
        $products = $page->items->toArray();

        expect($products)->toHaveCount(3);
        // Indexed products first in descending price order
        expect($products[0]->id)->toBe($expensive->id);
        expect($products[1]->id)->toBe($cheap->id);
        // NULL-priced product comes last
        expect($products[2]->id)->toBe($noIndex->id);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('preserves the selected sort across pagination pages', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(e2ePriceProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConnectionInterface $conn */
        $conn = $store->container()->get(ConnectionInterface::class);

        $registry = e2ePriceMakeRegistry();
        $resolver = e2ePriceMakeResolver($registry, ['defaultPageSize' => 3, 'allowedPageSizes' => [3, 5, 10]]);
        $service  = e2ePriceMakeServiceFromConn($conn);

        $categoryFactory = CategoryFactory::new($store);
        $productFactory  = ProductFactory::new($store);

        $category = $categoryFactory->withName('Multi-Page Price Category')->create();

        $prices = ['70.00', '10.00', '50.00', '30.00', '90.00', '20.00', '60.00'];
        $productIds = [];

        foreach ($prices as $i => $price) {
            $product = $productFactory->withName("Product $i")->withSku("E2E-PAGE-$i")->create();
            $conn->execute(
                'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
                [(int) $product->id, (int) $category->id, $i],
            );
            e2ePriceSetIndexEntry($conn, (int) $product->id, $price);
            $productIds[] = (int) $product->id;
        }

        $optionsPage1  = $resolver->resolve(page: 1, size: 3, sort: 'price_asc');
        $page1         = $service->paginatedProductsInCategory((int) $category->id, $optionsPage1);
        $productsPage1 = $page1->items->toArray();

        $optionsPage2  = $resolver->resolve(page: 2, size: 3, sort: 'price_asc');
        $page2         = $service->paginatedProductsInCategory((int) $category->id, $optionsPage2);
        $productsPage2 = $page2->items->toArray();

        $allIds = array_map(fn ($p) => $p->id, array_merge($productsPage1, $productsPage2));

        expect(array_unique($allIds))->toHaveCount(count($allIds));

        $page1Prices = array_map(function ($p) use ($prices, $productIds): float {
            $idx = array_search($p->id, $productIds, true);

            return (float) $prices[$idx];
        }, $productsPage1);

        $page2Prices = array_map(function ($p) use ($prices, $productIds): float {
            $idx = array_search($p->id, $productIds, true);

            return (float) $prices[$idx];
        }, $productsPage2);

        expect(max($page1Prices))->toBeLessThan(min($page2Prices));
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
