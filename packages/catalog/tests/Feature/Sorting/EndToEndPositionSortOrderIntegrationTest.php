<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Feature\Sorting;

require_once __DIR__ . '/../Helpers/PostgresTestConnection.php';

use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;
use Markommerce\Catalog\Exceptions\InvalidPaginationConfigException;
use Markommerce\Catalog\Pagination\PaginationOptionsResolver;
use Markommerce\Catalog\Repositories\CategoryRepository;
use Markommerce\Catalog\Repositories\ProductCategoryAssignmentRepository;
use Markommerce\Catalog\Repositories\ProductRepository;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Sorting\CategorySortOrderRegistry;
use Markommerce\Catalog\Sorting\ColumnSortOrder;
use Markommerce\Catalog\Tests\Feature\Helpers\PostgresTestConnection;
use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Criteria\Position\PositionCodec;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Strategy\KeysetPaginationStrategy;

// ─── Schema helpers ───────────────────────────────────────────────────────────

function e2ePositionCreateSchema(PostgresTestConnection $conn): void
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
                  AND indexname = 'uniq_e2e_position_prod_cat'
            ) THEN
                CREATE UNIQUE INDEX uniq_e2e_position_prod_cat
                ON catalog_product_category (product_id, category_id);
            END IF;
        END
        \$\$",
    );
}

function e2ePositionDropSchema(PostgresTestConnection $conn): void
{
    $conn->execute('DROP TABLE IF EXISTS catalog_product_category CASCADE');
    $conn->execute('DROP TABLE IF EXISTS catalog_products CASCADE');
    $conn->execute('DROP TABLE IF EXISTS catalog_categories CASCADE');
}

// ─── Factory helpers ──────────────────────────────────────────────────────────

function e2ePositionMakeService(PostgresTestConnection $conn): CategoryAssignmentService
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

/**
 * @param array<string, mixed> $configOverrides
 */
function e2ePositionMakeResolver(
    CategorySortOrderRegistry $registry,
    array $configOverrides = [],
): PaginationOptionsResolver {
    $defaults = [
        'defaultPageSize'  => 10,
        'allowedPageSizes' => [10, 20, 50],
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

function e2ePositionMakeRegistry(): CategorySortOrderRegistry
{
    $registry = new CategorySortOrderRegistry();
    $registry->register(new ColumnSortOrder(
        key: 'position',
        label: 'Position',
        column: 'catalog_product_category.position',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    ), 0);

    return $registry;
}

function e2ePositionInsertProduct(PostgresTestConnection $conn, string $name, string $sku): int
{
    $conn->execute('INSERT INTO catalog_products (name, sku) VALUES (?, ?)', [$name, $sku]);
    $result = $conn->query('SELECT id FROM catalog_products WHERE sku = ? LIMIT 1', [$sku]);

    return (int) $result[0]['id'];
}

function e2ePositionInsertCategory(PostgresTestConnection $conn, string $name): int
{
    $conn->execute('INSERT INTO catalog_categories (name) VALUES (?)', [$name]);
    $result = $conn->query('SELECT id FROM catalog_categories WHERE name = ? LIMIT 1', [$name]);

    return (int) $result[0]['id'];
}

function e2ePositionAssignProduct(
    PostgresTestConnection $conn,
    int $productId,
    int $categoryId,
    int $position,
): void {
    $conn->execute(
        'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
        [$productId, $categoryId, $position],
    );
}

// ─── Shared lifecycle ─────────────────────────────────────────────────────────

beforeEach(function (): void {
    PostgresTestConnection::skipIfUnavailable();

    $this->conn = new PostgresTestConnection();

    e2ePositionDropSchema($this->conn);
    e2ePositionCreateSchema($this->conn);
});

afterEach(function (): void {
    if (isset($this->conn)) {
        e2ePositionDropSchema($this->conn);
    }
});

// ─── Tests ────────────────────────────────────────────────────────────────────

it('lists category products in assignment position order by default', function (): void {
    $registry = e2ePositionMakeRegistry();
    $resolver = e2ePositionMakeResolver($registry);
    $service  = e2ePositionMakeService($this->conn);

    $categoryId = e2ePositionInsertCategory($this->conn, 'Default Sort Category');

    $productId3 = e2ePositionInsertProduct($this->conn, 'Third', 'E2E-POS-003');
    $productId1 = e2ePositionInsertProduct($this->conn, 'First', 'E2E-POS-001');
    $productId2 = e2ePositionInsertProduct($this->conn, 'Second', 'E2E-POS-002');

    e2ePositionAssignProduct($this->conn, $productId3, $categoryId, 30);
    e2ePositionAssignProduct($this->conn, $productId1, $categoryId, 10);
    e2ePositionAssignProduct($this->conn, $productId2, $categoryId, 20);

    // Drive through the real resolve → apply path (no sort param = default position)
    $options  = $resolver->resolve(page: 1, size: 10, sort: null);
    $page     = $service->paginatedProductsInCategory($categoryId, $options);
    $products = $page->items->toArray();

    expect($products)->toHaveCount(3)
        ->and($products[0]->id)->toBe($productId1)
        ->and($products[1]->id)->toBe($productId2)
        ->and($products[2]->id)->toBe($productId3);
})->group('integration-destructive');

it('fails loudly when a non-keyset sort is requested under the keyset strategy', function (): void {
    $registry = e2ePositionMakeRegistry();
    $resolver = e2ePositionMakeResolver($registry, [
        'strategy'     => 'keyset',
        'presentation' => 'load_more',
        'defaultSort'  => 'position',
    ]);

    // position does not support keyset → must throw with non-empty message/context/suggestion
    $caught = null;

    try {
        $resolver->resolve(page: 1, size: 10, sort: 'position');
    } catch (InvalidPaginationConfigException $e) {
        $caught = $e;
    }

    expect($caught)->not->toBeNull()
        ->and($caught->getMessage())->not->toBeEmpty()
        ->and($caught->getContext())->not->toBeEmpty()
        ->and($caught->getSuggestion())->not->toBeEmpty();
});
