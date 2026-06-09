<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndexMarket\Tests\Feature\Sorting;

require_once __DIR__ . '/../Helpers/PostgresTestConnection.php';

use Marko\Config\ConfigRepository;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;
use Markommerce\Catalog\Pagination\CountMode;
use Markommerce\Catalog\Pagination\PaginationPresentation;
use Markommerce\Catalog\Pagination\PaginationStrategyKind;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;
use Markommerce\Catalog\Repositories\CategoryRepository;
use Markommerce\Catalog\Repositories\ProductCategoryAssignmentRepository;
use Markommerce\Catalog\Repositories\ProductRepository;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Sorting\CategorySortOrderInterface;
use Markommerce\CatalogPriceIndexMarket\Sorting\ScopedAscendingIndexedPriceSortOrder;
use Markommerce\CatalogPriceIndexMarket\Tests\Feature\Helpers\PostgresTestConnection;
use Markommerce\Criteria\Position\PositionCodec;
use Markommerce\Criteria\Strategy\KeysetPaginationStrategy;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Registry\PhpScopeRegistry;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function scopedPriceCreateSchema(PostgresTestConnection $conn): void
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
                  AND indexname = 'uniq_cat_prod_cat_sptest'
            ) THEN
                CREATE UNIQUE INDEX uniq_cat_prod_cat_sptest
                ON catalog_product_category (product_id, category_id);
            END IF;
        END
        \$\$",
    );

    $conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_product_price_index (
            id            SERIAL PRIMARY KEY,
            product_id    INTEGER NOT NULL UNIQUE REFERENCES catalog_products(id) ON DELETE CASCADE,
            amount        DECIMAL(20,4),
            currency_code CHAR(3) NOT NULL DEFAULT \'USD\',
            scopes        JSONB
        )',
    );
}

function scopedPriceDropSchema(PostgresTestConnection $conn): void
{
    $conn->execute('DROP TABLE IF EXISTS catalog_product_price_index CASCADE');
    $conn->execute('DROP TABLE IF EXISTS catalog_product_category CASCADE');
    $conn->execute('DROP TABLE IF EXISTS catalog_products CASCADE');
    $conn->execute('DROP TABLE IF EXISTS catalog_categories CASCADE');
}

function scopedPriceMakeService(PostgresTestConnection $conn): CategoryAssignmentService
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

function scopedPriceInsertProduct(PostgresTestConnection $conn, string $name, string $sku): int
{
    $conn->execute('INSERT INTO catalog_products (name, sku) VALUES (?, ?)', [$name, $sku]);
    $result = $conn->query('SELECT id FROM catalog_products WHERE sku = ? LIMIT 1', [$sku]);

    return (int) $result[0]['id'];
}

function scopedPriceInsertCategory(PostgresTestConnection $conn, string $name): int
{
    $conn->execute('INSERT INTO catalog_categories (name) VALUES (?)', [$name]);
    $result = $conn->query('SELECT id FROM catalog_categories WHERE name = ? LIMIT 1', [$name]);

    return (int) $result[0]['id'];
}

function scopedPriceAssignProduct(PostgresTestConnection $conn, int $productId, int $categoryId): void
{
    $conn->execute(
        'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, 0)',
        [$productId, $categoryId],
    );
}

function scopedPriceSetPrice(PostgresTestConnection $conn, int $productId, string $amount, ?string $scopesJson = null): void
{
    $conn->execute(
        "INSERT INTO catalog_product_price_index (product_id, amount, currency_code, scopes)
         VALUES (?, ?, 'USD', ?)
         ON CONFLICT (product_id) DO UPDATE SET amount = EXCLUDED.amount, scopes = EXCLUDED.scopes",
        [$productId, $amount, $scopesJson],
    );
}

function scopedPriceMakeOffsetOptions(CategorySortOrderInterface $sortOrder): ResolvedPaginationOptions
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

function scopedPriceMakeRegistry(): PhpScopeRegistry
{
    $config = new ConfigRepository([
        'scope' => [
            'axes' => [
                'market' => [
                    'default' => 'default',
                    'scopes'  => [
                        'default' => [],
                        'us'      => [],
                        'eu'      => [],
                    ],
                ],
            ],
        ],
    ]);

    return new PhpScopeRegistry($config);
}

// ─── Shared lifecycle ─────────────────────────────────────────────────────────

beforeEach(function (): void {
    PostgresTestConnection::skipIfUnavailable();

    $this->conn = new PostgresTestConnection();

    scopedPriceDropSchema($this->conn);
    scopedPriceCreateSchema($this->conn);
});

afterEach(function (): void {
    if (isset($this->conn)) {
        scopedPriceDropSchema($this->conn);
    }
});

// ─── Tests ────────────────────────────────────────────────────────────────────

it('it orders by the active market override amount when present', function (): void {
    $service    = scopedPriceMakeService($this->conn);
    $categoryId = scopedPriceInsertCategory($this->conn, 'Market Asc Category');

    // Base price is 9.99 and 49.99, but market:us prices are 100.00 and 5.00
    // so ascending order by market:us price should be: marketCheap ($5), marketExpensive ($100)
    $marketCheapId     = scopedPriceInsertProduct($this->conn, 'Market Cheap Product', 'MKTCHEAP-001');
    $marketExpensiveId = scopedPriceInsertProduct($this->conn, 'Market Expensive Product', 'MKTEXP-001');

    scopedPriceAssignProduct($this->conn, $marketCheapId, $categoryId);
    scopedPriceAssignProduct($this->conn, $marketExpensiveId, $categoryId);

    // Market cheap product has a higher base price but lower market:us override
    scopedPriceSetPrice($this->conn, $marketCheapId, '49.99', '{"market:us":{"amount":"5.0000"}}');
    scopedPriceSetPrice($this->conn, $marketExpensiveId, '9.99', '{"market:us":{"amount":"100.0000"}}');

    $registry  = scopedPriceMakeRegistry();
    $context   = new ScopeContext($registry);
    $context->in('market', 'us');
    $sortOrder = new ScopedAscendingIndexedPriceSortOrder($registry, $context);

    $options  = scopedPriceMakeOffsetOptions($sortOrder);
    $page     = $service->paginatedProductsInCategory($categoryId, $options);
    $products = $page->items->toArray();

    expect($products)->toHaveCount(2);
    // Market cheap ($5 us override) should come first in ascending order
    expect($products[0]->id)->toBe($marketCheapId);
    expect($products[1]->id)->toBe($marketExpensiveId);
})->group('integration-destructive');

it('it falls back to the base amount when the active market has no override', function (): void {
    $service    = scopedPriceMakeService($this->conn);
    $categoryId = scopedPriceInsertCategory($this->conn, 'Fallback Category');

    $cheapId     = scopedPriceInsertProduct($this->conn, 'Cheap Fallback Product', 'FBCHEAP-001');
    $expensiveId = scopedPriceInsertProduct($this->conn, 'Expensive Fallback Product', 'FBEXP-001');

    scopedPriceAssignProduct($this->conn, $cheapId, $categoryId);
    scopedPriceAssignProduct($this->conn, $expensiveId, $categoryId);

    // No market:us override — only base amounts
    scopedPriceSetPrice($this->conn, $cheapId, '10.00', null);
    scopedPriceSetPrice($this->conn, $expensiveId, '50.00', null);

    $registry  = scopedPriceMakeRegistry();
    $context   = new ScopeContext($registry);
    $context->in('market', 'us');
    $sortOrder = new ScopedAscendingIndexedPriceSortOrder($registry, $context);

    $options  = scopedPriceMakeOffsetOptions($sortOrder);
    $page     = $service->paginatedProductsInCategory($categoryId, $options);
    $products = $page->items->toArray();

    expect($products)->toHaveCount(2);
    // Without market override, COALESCE falls back to base amount
    expect($products[0]->id)->toBe($cheapId);
    expect($products[1]->id)->toBe($expensiveId);
})->group('integration-destructive');

it('it still places products with no price last', function (): void {
    $service    = scopedPriceMakeService($this->conn);
    $categoryId = scopedPriceInsertCategory($this->conn, 'No Price Last Category');

    $pricedId  = scopedPriceInsertProduct($this->conn, 'Priced Product', 'PRICED-001');
    $noPriceId = scopedPriceInsertProduct($this->conn, 'No Price Product', 'NOPRICE-SP-001');

    scopedPriceAssignProduct($this->conn, $pricedId, $categoryId);
    scopedPriceAssignProduct($this->conn, $noPriceId, $categoryId);

    scopedPriceSetPrice($this->conn, $pricedId, '20.00', '{"market:us":{"amount":"20.0000"}}');
    // $noPriceId has no price index row — NULL via LEFT JOIN

    $registry  = scopedPriceMakeRegistry();
    $context   = new ScopeContext($registry);
    $context->in('market', 'us');
    $sortOrder = new ScopedAscendingIndexedPriceSortOrder($registry, $context);

    $options  = scopedPriceMakeOffsetOptions($sortOrder);
    $page     = $service->paginatedProductsInCategory($categoryId, $options);
    $products = $page->items->toArray();

    expect($products)->toHaveCount(2);
    expect($products[0]->id)->toBe($pricedId);
    expect($products[1]->id)->toBe($noPriceId);
})->group('integration-destructive');

it('it casts the json override amount to numeric so 100 sorts after 9', function (): void {
    $service    = scopedPriceMakeService($this->conn);
    $categoryId = scopedPriceInsertCategory($this->conn, 'Numeric Cast Category');

    // These amounts would sort incorrectly as text: "100" < "9" lexicographically
    $nineId    = scopedPriceInsertProduct($this->conn, 'Nine Dollar Product', 'NINE-001');
    $hundredId = scopedPriceInsertProduct($this->conn, 'Hundred Dollar Product', 'HUNDRED-001');

    scopedPriceAssignProduct($this->conn, $nineId, $categoryId);
    scopedPriceAssignProduct($this->conn, $hundredId, $categoryId);

    scopedPriceSetPrice($this->conn, $nineId, '9.00', '{"market:us":{"amount":"9.0000"}}');
    scopedPriceSetPrice($this->conn, $hundredId, '100.00', '{"market:us":{"amount":"100.0000"}}');

    $registry  = scopedPriceMakeRegistry();
    $context   = new ScopeContext($registry);
    $context->in('market', 'us');
    $sortOrder = new ScopedAscendingIndexedPriceSortOrder($registry, $context);

    $options  = scopedPriceMakeOffsetOptions($sortOrder);
    $page     = $service->paginatedProductsInCategory($categoryId, $options);
    $products = $page->items->toArray();

    expect($products)->toHaveCount(2);
    // With ::numeric cast, 9 < 100 (not "100" < "9" as text comparison)
    expect($products[0]->id)->toBe($nineId);
    expect($products[1]->id)->toBe($hundredId);
})->group('integration-destructive');

it('it keeps the price index left join in the prepared query', function (): void {
    $service    = scopedPriceMakeService($this->conn);
    $categoryId = scopedPriceInsertCategory($this->conn, 'Left Join Category');

    $productId = scopedPriceInsertProduct($this->conn, 'Left Join Product', 'LJOIN-001');
    scopedPriceAssignProduct($this->conn, $productId, $categoryId);
    scopedPriceSetPrice($this->conn, $productId, '15.00', '{"market:us":{"amount":"15.0000"}}');

    $noIndexId = scopedPriceInsertProduct($this->conn, 'No Index Product', 'LJOIN-002');
    scopedPriceAssignProduct($this->conn, $noIndexId, $categoryId);
    // No price index row for $noIndexId — LEFT JOIN should still return this product

    $registry  = scopedPriceMakeRegistry();
    $context   = new ScopeContext($registry);
    $context->in('market', 'us');
    $sortOrder = new ScopedAscendingIndexedPriceSortOrder($registry, $context);

    $options  = scopedPriceMakeOffsetOptions($sortOrder);
    $page     = $service->paginatedProductsInCategory($categoryId, $options);
    $products = $page->items->toArray();

    // Both products should appear (LEFT JOIN, not INNER JOIN)
    expect($products)->toHaveCount(2);
})->group('integration-destructive');
