<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndex\Tests\Feature\Sorting;

require_once __DIR__ . '/../Helpers/PostgresTestConnection.php';

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
use Markommerce\CatalogPriceIndex\Sorting\AscendingIndexedPriceSortOrder;
use Markommerce\CatalogPriceIndex\Sorting\DescendingIndexedPriceSortOrder;
use Markommerce\CatalogPriceIndex\Tests\Feature\Helpers\PostgresTestConnection;
use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Criteria\Position\PositionCodec;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Strategy\KeysetPaginationStrategy;

// ─── Schema helpers ───────────────────────────────────────────────────────────

function e2ePriceCreateSchema(PostgresTestConnection $conn): void
{
    $conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_products (
            id           SERIAL PRIMARY KEY,
            name         VARCHAR(255) NOT NULL,
            sku          VARCHAR(255) NOT NULL UNIQUE,
            description  TEXT,
            price_amount DECIMAL(20,4)
        )',
    );

    $conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_categories (
            id          SERIAL PRIMARY KEY,
            name        VARCHAR(255) NOT NULL,
            description TEXT
        )',
    );

    $conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_product_category (
            id          SERIAL PRIMARY KEY,
            product_id  INTEGER NOT NULL REFERENCES catalog_products(id) ON DELETE CASCADE,
            category_id INTEGER NOT NULL REFERENCES catalog_categories(id) ON DELETE CASCADE,
            position    INTEGER NOT NULL DEFAULT 0
        )',
    );

    $conn->execute(
        "DO \$\$
        BEGIN
            IF NOT EXISTS (
                SELECT 1 FROM pg_indexes
                WHERE tablename = 'catalog_product_category'
                  AND indexname = 'uniq_e2e_price_prod_cat'
            ) THEN
                CREATE UNIQUE INDEX uniq_e2e_price_prod_cat
                ON catalog_product_category (product_id, category_id);
            END IF;
        END
        \$\$",
    );

    $conn->execute(
        "CREATE TABLE IF NOT EXISTS catalog_product_price_index (
            id            SERIAL PRIMARY KEY,
            product_id    INTEGER NOT NULL UNIQUE REFERENCES catalog_products(id) ON DELETE CASCADE,
            amount        DECIMAL(20,4),
            currency_code CHAR(3) NOT NULL DEFAULT 'USD',
            scopes        JSONB
        )",
    );
}

function e2ePriceDropSchema(PostgresTestConnection $conn): void
{
    $conn->execute('DROP TABLE IF EXISTS catalog_product_price_index CASCADE');
    $conn->execute('DROP TABLE IF EXISTS catalog_product_category CASCADE');
    $conn->execute('DROP TABLE IF EXISTS catalog_products CASCADE');
    $conn->execute('DROP TABLE IF EXISTS catalog_categories CASCADE');
}

// ─── Factory helpers ──────────────────────────────────────────────────────────

function e2ePriceMakeService(PostgresTestConnection $conn): CategoryAssignmentService
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
    );
}

function e2ePriceMakeRegistry(): CategorySortOrderRegistry
{
    $registry = new CategorySortOrderRegistry();

    // catalog registers position
    $registry->register(new ColumnSortOrder(
        key: 'position',
        label: 'Position',
        column: 'catalog_product_category.position',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    ), 0);

    // catalog-price-index registers price_asc / price_desc
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

        public function resolved(string $configClass, string $field): mixed
        {
            return $this->values[$field] ?? null;
        }
    };

    return new PaginationOptionsResolver($configResolver, $registry);
}

function e2ePriceInsertProduct(PostgresTestConnection $conn, string $name, string $sku): int
{
    $conn->execute('INSERT INTO catalog_products (name, sku) VALUES (?, ?)', [$name, $sku]);
    $result = $conn->query('SELECT id FROM catalog_products WHERE sku = ? LIMIT 1', [$sku]);

    return (int) $result[0]['id'];
}

function e2ePriceInsertCategory(PostgresTestConnection $conn, string $name): int
{
    $conn->execute('INSERT INTO catalog_categories (name) VALUES (?)', [$name]);
    $result = $conn->query('SELECT id FROM catalog_categories WHERE name = ? LIMIT 1', [$name]);

    return (int) $result[0]['id'];
}

function e2ePriceAssignProduct(
    PostgresTestConnection $conn,
    int $productId,
    int $categoryId,
    int $position = 0,
): void {
    $conn->execute(
        'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
        [$productId, $categoryId, $position],
    );
}

function e2ePriceSetPrice(PostgresTestConnection $conn, int $productId, string $amount): void
{
    $conn->execute(
        "INSERT INTO catalog_product_price_index (product_id, amount, currency_code)
         VALUES (?, ?, 'USD')
         ON CONFLICT (product_id) DO UPDATE SET amount = EXCLUDED.amount",
        [$productId, $amount],
    );
}

// ─── Shared lifecycle ─────────────────────────────────────────────────────────

beforeEach(function (): void {
    PostgresTestConnection::skipIfUnavailable();

    $this->conn = new PostgresTestConnection();

    e2ePriceDropSchema($this->conn);
    e2ePriceCreateSchema($this->conn);
});

afterEach(function (): void {
    if (isset($this->conn)) {
        e2ePriceDropSchema($this->conn);
    }
});

// ─── Tests ────────────────────────────────────────────────────────────────────

it('lists category products ascending by indexed price when price_asc is selected', function (): void {
    $registry = e2ePriceMakeRegistry();
    $resolver = e2ePriceMakeResolver($registry);
    $service  = e2ePriceMakeService($this->conn);

    $categoryId = e2ePriceInsertCategory($this->conn, 'Asc Price Category');

    $cheapId    = e2ePriceInsertProduct($this->conn, 'Budget Widget', 'E2E-ASC-CHEAP');
    $midId      = e2ePriceInsertProduct($this->conn, 'Mid Widget', 'E2E-ASC-MID');
    $expensiveId = e2ePriceInsertProduct($this->conn, 'Premium Widget', 'E2E-ASC-PREM');

    e2ePriceAssignProduct($this->conn, $cheapId, $categoryId);
    e2ePriceAssignProduct($this->conn, $midId, $categoryId);
    e2ePriceAssignProduct($this->conn, $expensiveId, $categoryId);

    e2ePriceSetPrice($this->conn, $cheapId, '5.00');
    e2ePriceSetPrice($this->conn, $midId, '25.00');
    e2ePriceSetPrice($this->conn, $expensiveId, '99.00');

    $options  = $resolver->resolve(page: 1, size: 10, sort: 'price_asc');
    $page     = $service->paginatedProductsInCategory($categoryId, $options);
    $products = $page->items->toArray();

    expect($products)->toHaveCount(3)
        ->and($products[0]->id)->toBe($cheapId)
        ->and($products[1]->id)->toBe($midId)
        ->and($products[2]->id)->toBe($expensiveId);
})->group('integration-destructive');

it('lists category products descending by indexed price when price_desc is selected', function (): void {
    $registry = e2ePriceMakeRegistry();
    $resolver = e2ePriceMakeResolver($registry);
    $service  = e2ePriceMakeService($this->conn);

    $categoryId = e2ePriceInsertCategory($this->conn, 'Desc Price Category');

    $cheapId    = e2ePriceInsertProduct($this->conn, 'Budget Item', 'E2E-DESC-CHEAP');
    $midId      = e2ePriceInsertProduct($this->conn, 'Mid Item', 'E2E-DESC-MID');
    $expensiveId = e2ePriceInsertProduct($this->conn, 'Premium Item', 'E2E-DESC-PREM');

    e2ePriceAssignProduct($this->conn, $cheapId, $categoryId);
    e2ePriceAssignProduct($this->conn, $midId, $categoryId);
    e2ePriceAssignProduct($this->conn, $expensiveId, $categoryId);

    e2ePriceSetPrice($this->conn, $cheapId, '5.00');
    e2ePriceSetPrice($this->conn, $midId, '25.00');
    e2ePriceSetPrice($this->conn, $expensiveId, '99.00');

    $options  = $resolver->resolve(page: 1, size: 10, sort: 'price_desc');
    $page     = $service->paginatedProductsInCategory($categoryId, $options);
    $products = $page->items->toArray();

    expect($products)->toHaveCount(3)
        ->and($products[0]->id)->toBe($expensiveId)
        ->and($products[1]->id)->toBe($midId)
        ->and($products[2]->id)->toBe($cheapId);
})->group('integration-destructive');

it('lists non-indexed products after indexed ones for both price directions', function (): void {
    $registry = e2ePriceMakeRegistry();
    $resolver = e2ePriceMakeResolver($registry);
    $service  = e2ePriceMakeService($this->conn);

    $categoryId = e2ePriceInsertCategory($this->conn, 'Null Price Category');

    $indexedCheapId   = e2ePriceInsertProduct($this->conn, 'Indexed Cheap', 'E2E-NULL-CHEAP');
    $indexedExpId     = e2ePriceInsertProduct($this->conn, 'Indexed Expensive', 'E2E-NULL-EXP');
    $unindexedAId     = e2ePriceInsertProduct($this->conn, 'Unindexed A', 'E2E-NULL-UNIDX-A');
    $unindexedBId     = e2ePriceInsertProduct($this->conn, 'Unindexed B', 'E2E-NULL-UNIDX-B');

    e2ePriceAssignProduct($this->conn, $indexedCheapId, $categoryId);
    e2ePriceAssignProduct($this->conn, $indexedExpId, $categoryId);
    e2ePriceAssignProduct($this->conn, $unindexedAId, $categoryId);
    e2ePriceAssignProduct($this->conn, $unindexedBId, $categoryId);

    // Only two products have price index entries
    e2ePriceSetPrice($this->conn, $indexedCheapId, '10.00');
    e2ePriceSetPrice($this->conn, $indexedExpId, '50.00');
    // $unindexedAId and $unindexedBId have no price index row → NULL via LEFT JOIN

    // ASC: indexed first (cheap → expensive), then NULL-priced last
    $optionsAsc  = $resolver->resolve(page: 1, size: 10, sort: 'price_asc');
    $pageAsc     = $service->paginatedProductsInCategory($categoryId, $optionsAsc);
    $productsAsc = $pageAsc->items->toArray();

    expect($productsAsc)->toHaveCount(4);
    // First two must be indexed (in any order among themselves, but before unindexed)
    $indexedIds = [$indexedCheapId, $indexedExpId];
    expect(in_array($productsAsc[0]->id, $indexedIds, true))->toBeTrue();
    expect(in_array($productsAsc[1]->id, $indexedIds, true))->toBeTrue();
    // Last two must be unindexed
    $unindexedIds = [$unindexedAId, $unindexedBId];
    expect(in_array($productsAsc[2]->id, $unindexedIds, true))->toBeTrue();
    expect(in_array($productsAsc[3]->id, $unindexedIds, true))->toBeTrue();

    // DESC: indexed first (expensive → cheap), then NULL-priced last
    $optionsDesc  = $resolver->resolve(page: 1, size: 10, sort: 'price_desc');
    $pageDesc     = $service->paginatedProductsInCategory($categoryId, $optionsDesc);
    $productsDesc = $pageDesc->items->toArray();

    expect($productsDesc)->toHaveCount(4);
    expect(in_array($productsDesc[0]->id, $indexedIds, true))->toBeTrue();
    expect(in_array($productsDesc[1]->id, $indexedIds, true))->toBeTrue();
    expect(in_array($productsDesc[2]->id, $unindexedIds, true))->toBeTrue();
    expect(in_array($productsDesc[3]->id, $unindexedIds, true))->toBeTrue();
})->group('integration-destructive');

it('preserves the selected sort across pagination pages', function (): void {
    $registry = e2ePriceMakeRegistry();
    // Use page size 3 so 7 products span multiple pages
    $resolver = e2ePriceMakeResolver($registry, ['defaultPageSize' => 3, 'allowedPageSizes' => [3, 5, 10]]);
    $service  = e2ePriceMakeService($this->conn);

    $categoryId = e2ePriceInsertCategory($this->conn, 'Multi-Page Price Category');

    // Insert 7 products with distinct prices; track by price for easy ordering assertions
    $prices = ['70.00', '10.00', '50.00', '30.00', '90.00', '20.00', '60.00'];
    $productIds = [];

    foreach ($prices as $i => $price) {
        $productId = e2ePriceInsertProduct($this->conn, "Product $i", "E2E-PAGE-{$i}");
        e2ePriceAssignProduct($this->conn, $productId, $categoryId, $i);
        e2ePriceSetPrice($this->conn, $productId, $price);
        $productIds[] = $productId;
    }

    // Resolve page 1 with price_asc
    $optionsPage1  = $resolver->resolve(page: 1, size: 3, sort: 'price_asc');
    $page1         = $service->paginatedProductsInCategory($categoryId, $optionsPage1);
    $productsPage1 = $page1->items->toArray();

    // Resolve page 2 with the same sort
    $optionsPage2  = $resolver->resolve(page: 2, size: 3, sort: 'price_asc');
    $page2         = $service->paginatedProductsInCategory($categoryId, $optionsPage2);
    $productsPage2 = $page2->items->toArray();

    // Collect all IDs across both pages
    $allIds = array_map(fn ($p) => $p->id, array_merge($productsPage1, $productsPage2));

    // No duplicates between page 1 and page 2
    expect(array_unique($allIds))->toHaveCount(count($allIds));

    // Page 1 items must all be cheaper than page 2 items (global ascending price order preserved)
    $page1Prices = array_map(function ($p) use ($prices, $productIds): float {
        $idx = array_search($p->id, $productIds, true);

        return (float) $prices[$idx];
    }, $productsPage1);

    $page2Prices = array_map(function ($p) use ($prices, $productIds): float {
        $idx = array_search($p->id, $productIds, true);

        return (float) $prices[$idx];
    }, $productsPage2);

    // All prices on page 1 must be lower than all prices on page 2
    expect(max($page1Prices))->toBeLessThan(min($page2Prices));
})->group('integration-destructive');
