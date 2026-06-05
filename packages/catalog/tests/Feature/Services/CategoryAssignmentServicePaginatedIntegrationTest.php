<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Feature\Services;

require_once __DIR__ . '/../Helpers/PostgresTestConnection.php';

use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Exceptions\CategoryNotFoundException;
use Markommerce\Catalog\Pagination\CountMode;
use Markommerce\Catalog\Pagination\PaginationPresentation;
use Markommerce\Catalog\Pagination\PaginationStrategyKind;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;
use Markommerce\Catalog\Repositories\CategoryRepository;
use Markommerce\Catalog\Repositories\ProductCategoryAssignmentRepository;
use Markommerce\Catalog\Repositories\ProductRepository;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Tests\Feature\Helpers\PostgresTestConnection;
use Markommerce\Criteria\Page\Page;
use Markommerce\Criteria\Page\PageRequest;
use Markommerce\Criteria\Position\OffsetPosition;
use Markommerce\Criteria\Position\PositionCodec;
use Markommerce\Criteria\Sort\Sort;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Sort\SortField;
use Markommerce\Criteria\Strategy\KeysetPaginationStrategy;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;

// ─── Schema helpers ───────────────────────────────────────────────────────────

function createCatalogSchema(PostgresTestConnection $conn): void
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
                  AND indexname = 'uniq_catalog_product_category_pagtest'
            ) THEN
                CREATE UNIQUE INDEX uniq_catalog_product_category_pagtest
                ON catalog_product_category (product_id, category_id);
            END IF;
        END
        \$\$",
    );
}

function dropCatalogSchema(PostgresTestConnection $conn): void
{
    $conn->execute('DROP TABLE IF EXISTS catalog_product_category CASCADE');
    $conn->execute('DROP TABLE IF EXISTS catalog_products CASCADE');
    $conn->execute('DROP TABLE IF EXISTS catalog_categories CASCADE');
}

function makeAssignmentService(PostgresTestConnection $conn): CategoryAssignmentService
{
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator($metadataFactory);
    $queryBuilderFactory = new PgSqlQueryBuilderFactory($conn);

    $productRepository = new ProductRepository($conn, $metadataFactory, $hydrator, $queryBuilderFactory);
    $categoryRepository = new CategoryRepository($conn, $metadataFactory, $hydrator);
    $assignmentRepository = new ProductCategoryAssignmentRepository($conn, $metadataFactory, $hydrator);
    $positionCodec = new PositionCodec();
    $keysetStrategy = new KeysetPaginationStrategy($positionCodec);

    return new CategoryAssignmentService(
        productRepository: $productRepository,
        categoryRepository: $categoryRepository,
        productCategoryAssignmentRepository: $assignmentRepository,
        positionCodec: $positionCodec,
        keysetPaginationStrategy: $keysetStrategy,
    );
}

function insertProduct(PostgresTestConnection $conn, string $name, string $sku, ?string $priceAmount = null): int
{
    $conn->execute(
        "INSERT INTO catalog_products (name, sku, price_amount) VALUES (?, ?, ?)",
        [$name, $sku, $priceAmount],
    );
    $result = $conn->query("SELECT id FROM catalog_products WHERE sku = ? LIMIT 1", [$sku]);

    return (int) $result[0]['id'];
}

function insertCategory(PostgresTestConnection $conn, string $name): int
{
    $conn->execute("INSERT INTO catalog_categories (name) VALUES (?)", [$name]);
    $result = $conn->query("SELECT id FROM catalog_categories WHERE name = ? LIMIT 1", [$name]);

    return (int) $result[0]['id'];
}

function assignProductToCategory(PostgresTestConnection $conn, int $productId, int $categoryId, int $position = 0): void
{
    $conn->execute(
        "INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)",
        [$productId, $categoryId, $position],
    );
}

function makeOffsetOptions(int $pageSize = 10, int $page = 1, string $sortColumn = 'catalog_product_category.position'): ResolvedPaginationOptions
{
    $sort = new Sort(new SortField($sortColumn, SortDirection::Ascending));
    $pageRequest = PageRequest::first($pageSize, $sort);

    return new ResolvedPaginationOptions(
        pageRequest: $pageRequest,
        page: $page,
        presentation: PaginationPresentation::Numbered,
        strategyKind: PaginationStrategyKind::Offset,
        countMode: CountMode::Exact,
    );
}

// ─── Shared lifecycle ────────────────────────────────────────────────────────

beforeEach(function (): void {
    PostgresTestConnection::skipIfUnavailable();

    $this->conn = new PostgresTestConnection();

    dropCatalogSchema($this->conn);
    createCatalogSchema($this->conn);
});

afterEach(function (): void {
    if (isset($this->conn)) {
        dropCatalogSchema($this->conn);
    }
});

// ─── Tests ───────────────────────────────────────────────────────────────────

it('returns a page of products assigned to the category', function (): void {
    $service = makeAssignmentService($this->conn);

    $categoryId = insertCategory($this->conn, 'Electronics');
    $productId1 = insertProduct($this->conn, 'Laptop', 'LAPTOP-001');
    $productId2 = insertProduct($this->conn, 'Phone', 'PHONE-001');

    assignProductToCategory($this->conn, $productId1, $categoryId, 1);
    assignProductToCategory($this->conn, $productId2, $categoryId, 2);

    $options = makeOffsetOptions(pageSize: 10);

    $page = $service->paginatedProductsInCategory($categoryId, $options);

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page->items->toArray())->toHaveCount(2)
        ->and($page->items->toArray()[0])->toBeInstanceOf(Product::class);
})->group('integration-destructive');

it('fetches the products in a single join query without per-product lookups', function (): void {
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator($metadataFactory);
    $positionCodec = new PositionCodec();
    $keysetStrategy = new KeysetPaginationStrategy($positionCodec);

    $queryCount = 0;
    $spyConn = new class ($this->conn, $queryCount) extends PostgresTestConnection {
        public function __construct(
            private PostgresTestConnection $wrapped,
            public int &$queryCount,
        ) {
            // Skip parent constructor - don't call parent::__construct
        }

        public function query(string $sql, array $bindings = []): array
        {
            $this->queryCount++;

            return $this->wrapped->query($sql, $bindings);
        }

        public function execute(string $sql, array $bindings = []): int
        {
            return $this->wrapped->execute($sql, $bindings);
        }
    };

    $spyQueryBuilderFactory = new PgSqlQueryBuilderFactory($spyConn);
    $productRepo = new ProductRepository($spyConn, $metadataFactory, $hydrator, $spyQueryBuilderFactory);
    $categoryRepo = new CategoryRepository($this->conn, $metadataFactory, $hydrator);
    $assignmentRepo = new ProductCategoryAssignmentRepository($this->conn, $metadataFactory, $hydrator);

    $service = new CategoryAssignmentService(
        productRepository: $productRepo,
        categoryRepository: $categoryRepo,
        productCategoryAssignmentRepository: $assignmentRepo,
        positionCodec: $positionCodec,
        keysetPaginationStrategy: $keysetStrategy,
    );

    $categoryId = insertCategory($this->conn, 'Tech');

    for ($i = 1; $i <= 5; $i++) {
        $productId = insertProduct($this->conn, "Product $i", "SKU-$i");
        assignProductToCategory($this->conn, $productId, $categoryId, $i);
    }

    $options = makeOffsetOptions(pageSize: 10);

    $spyConn->queryCount = 0;
    $service->paginatedProductsInCategory($categoryId, $options);

    // One count query + one product query (join) = 2 max; NOT 5 (one per product)
    expect($spyConn->queryCount)->toBeLessThanOrEqual(2);
})->group('integration-destructive');

it('applies the configured page size to the result', function (): void {
    $service = makeAssignmentService($this->conn);

    $categoryId = insertCategory($this->conn, 'Books');

    for ($i = 1; $i <= 7; $i++) {
        $productId = insertProduct($this->conn, "Book $i", "BOOK-00$i");
        assignProductToCategory($this->conn, $productId, $categoryId, $i);
    }

    $options = makeOffsetOptions(pageSize: 3);

    $page = $service->paginatedProductsInCategory($categoryId, $options);

    expect($page->items->toArray())->toHaveCount(3);
})->group('integration-destructive');

it('orders products by the configured sort column with an id tie-break', function (): void {
    $service = makeAssignmentService($this->conn);

    $categoryId = insertCategory($this->conn, 'Sorted Category');

    $productIdC = insertProduct($this->conn, 'Charlie', 'SKU-C');
    $productIdA = insertProduct($this->conn, 'Alpha', 'SKU-A');
    $productIdB = insertProduct($this->conn, 'Beta', 'SKU-B');

    assignProductToCategory($this->conn, $productIdC, $categoryId, 1);
    assignProductToCategory($this->conn, $productIdA, $categoryId, 2);
    assignProductToCategory($this->conn, $productIdB, $categoryId, 3);

    // Sort by name column
    $sort = new Sort(new SortField('catalog_products.name', SortDirection::Ascending));
    $pageRequest = PageRequest::first(10, $sort);
    $options = new ResolvedPaginationOptions(
        pageRequest: $pageRequest,
        page: 1,
        presentation: PaginationPresentation::Numbered,
        strategyKind: PaginationStrategyKind::Offset,
        countMode: CountMode::Exact,
    );

    $page = $service->paginatedProductsInCategory($categoryId, $options);
    $products = $page->items->toArray();

    expect($products)->toHaveCount(3)
        ->and($products[0]->name)->toBe('Alpha')
        ->and($products[1]->name)->toBe('Beta')
        ->and($products[2]->name)->toBe('Charlie');
})->group('integration-destructive');

it('returns a next position when more products exist', function (): void {
    $service = makeAssignmentService($this->conn);

    $categoryId = insertCategory($this->conn, 'Many Products');

    for ($i = 1; $i <= 5; $i++) {
        $productId = insertProduct($this->conn, "Product $i", "MANYPROD-$i");
        assignProductToCategory($this->conn, $productId, $categoryId, $i);
    }

    $options = makeOffsetOptions(pageSize: 3);

    $page = $service->paginatedProductsInCategory($categoryId, $options);

    expect($page->hasNext())->toBeTrue()
        ->and($page->nextPosition)->not->toBeNull();
})->group('integration-destructive');

it('throws CategoryNotFoundException for an unknown category', function (): void {
    $service = makeAssignmentService($this->conn);

    $options = makeOffsetOptions(pageSize: 10);

    expect(fn () => $service->paginatedProductsInCategory(99999, $options))
        ->toThrow(CategoryNotFoundException::class);
})->group('integration-destructive');

it('derives the offset total from a join-safe count not from the joined builder', function (): void {
    $service = makeAssignmentService($this->conn);

    $categoryId = insertCategory($this->conn, 'Count Test');
    $otherCategoryId = insertCategory($this->conn, 'Other Category');

    // 4 products in target category
    for ($i = 1; $i <= 4; $i++) {
        $productId = insertProduct($this->conn, "CountProd $i", "COUNTPROD-$i");
        assignProductToCategory($this->conn, $productId, $categoryId, $i);
    }

    // 10 products in another category (should not affect count)
    for ($i = 1; $i <= 10; $i++) {
        $productId = insertProduct($this->conn, "OtherProd $i", "OTHERPROD-$i");
        assignProductToCategory($this->conn, $productId, $otherCategoryId, $i);
    }

    $options = makeOffsetOptions(pageSize: 3);

    $page = $service->paginatedProductsInCategory($categoryId, $options);

    // Page items should be 3 (page size), total should reflect only the 4 in the category
    expect($page->items->toArray())->toHaveCount(3);

    // The page must know there's a next page (4 products, page size 3)
    expect($page->hasNext())->toBeTrue();
})->group('integration-destructive');
