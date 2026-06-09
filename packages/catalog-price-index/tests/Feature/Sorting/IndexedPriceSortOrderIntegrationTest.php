<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndex\Tests\Feature\Sorting;

require_once __DIR__ . '/../Helpers/PostgresTestConnection.php';

use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pagination\CountMode;
use Markommerce\Catalog\Pagination\PaginationPresentation;
use Markommerce\Catalog\Pagination\PaginationStrategyKind;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;
use Markommerce\Catalog\Repositories\CategoryRepository;
use Markommerce\Catalog\Repositories\ProductCategoryAssignmentRepository;
use Markommerce\Catalog\Repositories\ProductRepository;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Sorting\CategorySortOrderInterface;
use Markommerce\CatalogPriceIndex\Sorting\AscendingIndexedPriceSortOrder;
use Markommerce\CatalogPriceIndex\Sorting\DescendingIndexedPriceSortOrder;
use Markommerce\CatalogPriceIndex\Tests\Feature\Helpers\PostgresTestConnection;
use Markommerce\Criteria\Position\PositionCodec;
use Markommerce\Criteria\Strategy\KeysetPaginationStrategy;

// ─── Schema helpers ───────────────────────────────────────────────────────────

function priceIndexCreateSchema(PostgresTestConnection $conn): void
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
                  AND indexname = 'uniq_cat_prod_cat_pitest'
            ) THEN
                CREATE UNIQUE INDEX uniq_cat_prod_cat_pitest
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

function priceIndexDropSchema(PostgresTestConnection $conn): void
{
    $conn->execute('DROP TABLE IF EXISTS catalog_product_price_index CASCADE');
    $conn->execute('DROP TABLE IF EXISTS catalog_product_category CASCADE');
    $conn->execute('DROP TABLE IF EXISTS catalog_products CASCADE');
    $conn->execute('DROP TABLE IF EXISTS catalog_categories CASCADE');
}

function priceIndexMakeService(PostgresTestConnection $conn): CategoryAssignmentService
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

function priceIndexInsertProduct(PostgresTestConnection $conn, string $name, string $sku): int
{
    $conn->execute('INSERT INTO catalog_products (name, sku) VALUES (?, ?)', [$name, $sku]);
    $result = $conn->query('SELECT id FROM catalog_products WHERE sku = ? LIMIT 1', [$sku]);

    return (int) $result[0]['id'];
}

function priceIndexInsertCategory(PostgresTestConnection $conn, string $name): int
{
    $conn->execute('INSERT INTO catalog_categories (name) VALUES (?)', [$name]);
    $result = $conn->query('SELECT id FROM catalog_categories WHERE name = ? LIMIT 1', [$name]);

    return (int) $result[0]['id'];
}

function priceIndexAssignProduct(PostgresTestConnection $conn, int $productId, int $categoryId): void
{
    $conn->execute(
        'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, 0)',
        [$productId, $categoryId],
    );
}

function priceIndexSetPrice(PostgresTestConnection $conn, int $productId, string $amount): void
{
    $conn->execute(
        "INSERT INTO catalog_product_price_index (product_id, amount, currency_code)
         VALUES (?, ?, 'USD')
         ON CONFLICT (product_id) DO UPDATE SET amount = EXCLUDED.amount",
        [$productId, $amount],
    );
}

function priceIndexMakeOffsetOptions(CategorySortOrderInterface $sortOrder): ResolvedPaginationOptions
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

// ─── Shared lifecycle ─────────────────────────────────────────────────────────

beforeEach(function (): void {
    PostgresTestConnection::skipIfUnavailable();

    $this->conn = new PostgresTestConnection();

    priceIndexDropSchema($this->conn);
    priceIndexCreateSchema($this->conn);
});

afterEach(function (): void {
    if (isset($this->conn)) {
        priceIndexDropSchema($this->conn);
    }
});

// ─── Tests ────────────────────────────────────────────────────────────────────

it('it places non-indexed products last in ascending order', function (): void {
    $service    = priceIndexMakeService($this->conn);
    $categoryId = priceIndexInsertCategory($this->conn, 'Price Asc Category');

    $cheapId    = priceIndexInsertProduct($this->conn, 'Cheap Product', 'CHEAP-001');
    $expensiveId = priceIndexInsertProduct($this->conn, 'Expensive Product', 'EXPENSIVE-001');
    $noIndexId  = priceIndexInsertProduct($this->conn, 'No Price Product', 'NOPRICE-001');

    priceIndexAssignProduct($this->conn, $cheapId, $categoryId);
    priceIndexAssignProduct($this->conn, $expensiveId, $categoryId);
    priceIndexAssignProduct($this->conn, $noIndexId, $categoryId);

    priceIndexSetPrice($this->conn, $cheapId, '9.99');
    priceIndexSetPrice($this->conn, $expensiveId, '49.99');
    // $noIndexId has no price index row — NULL via LEFT JOIN

    $options = priceIndexMakeOffsetOptions(new AscendingIndexedPriceSortOrder());
    $page    = $service->paginatedProductsInCategory($categoryId, $options);
    $products = $page->items->toArray();

    expect($products)->toHaveCount(3);

    // Indexed products come first in ascending price order
    expect($products[0]->id)->toBe($cheapId);
    expect($products[1]->id)->toBe($expensiveId);
    // NULL-priced product comes last
    expect($products[2]->id)->toBe($noIndexId);
})->group('integration-destructive');

it('it places non-indexed products last in descending order', function (): void {
    $service    = priceIndexMakeService($this->conn);
    $categoryId = priceIndexInsertCategory($this->conn, 'Price Desc Category');

    $cheapId    = priceIndexInsertProduct($this->conn, 'Cheap Product', 'CHEAP-002');
    $expensiveId = priceIndexInsertProduct($this->conn, 'Expensive Product', 'EXPENSIVE-002');
    $noIndexId  = priceIndexInsertProduct($this->conn, 'No Price Product', 'NOPRICE-002');

    priceIndexAssignProduct($this->conn, $cheapId, $categoryId);
    priceIndexAssignProduct($this->conn, $expensiveId, $categoryId);
    priceIndexAssignProduct($this->conn, $noIndexId, $categoryId);

    priceIndexSetPrice($this->conn, $cheapId, '9.99');
    priceIndexSetPrice($this->conn, $expensiveId, '49.99');
    // $noIndexId has no price index row — NULL via LEFT JOIN

    $options  = priceIndexMakeOffsetOptions(new DescendingIndexedPriceSortOrder());
    $page     = $service->paginatedProductsInCategory($categoryId, $options);
    $products = $page->items->toArray();

    expect($products)->toHaveCount(3);

    // Indexed products come first in descending price order
    expect($products[0]->id)->toBe($expensiveId);
    expect($products[1]->id)->toBe($cheapId);
    // NULL-priced product comes last
    expect($products[2]->id)->toBe($noIndexId);
})->group('integration-destructive');
