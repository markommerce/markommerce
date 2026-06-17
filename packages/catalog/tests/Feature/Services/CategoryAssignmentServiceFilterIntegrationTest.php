<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Feature\Services;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;
use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Catalog\Filtering\FilterSelection;
use Markommerce\Catalog\Filtering\ProductListFilterInterface;
use Markommerce\Catalog\Filtering\ProductListFilterRegistry;
use Markommerce\Catalog\Pagination\CountMode;
use Markommerce\Catalog\Pagination\PaginationPresentation;
use Markommerce\Catalog\Pagination\PaginationStrategyKind;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;
use Markommerce\Catalog\Repositories\CategoryRepository;
use Markommerce\Catalog\Repositories\ProductCategoryAssignmentRepository;
use Markommerce\Catalog\Repositories\ProductRepository;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Sorting\ColumnSortOrder;
use Markommerce\Catalog\Tests\Support\CategoryFactory;
use Markommerce\Catalog\Tests\Support\ProductFactory;
use Markommerce\Criteria\Position\PositionCodec;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Strategy\KeysetPaginationStrategy;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function catFilterVendorDir(): string
{
    return dirname(__DIR__, 5) . '/vendor';
}

function catFilterProfile(): StoreProfile
{
    return StoreProfile::simple(catFilterVendorDir());
}

function makeFilterPositionSortOptions(int $pageSize = 10): ResolvedPaginationOptions
{
    return new ResolvedPaginationOptions(
        sortOrder: new ColumnSortOrder(
            key: 'position',
            label: 'Position',
            column: 'catalog_product_category.position',
            direction: SortDirection::Ascending,
            supportsKeyset: false,
        ),
        size: $pageSize,
        page: 1,
        presentation: PaginationPresentation::Numbered,
        strategyKind: PaginationStrategyKind::Offset,
        countMode: CountMode::Exact,
    );
}

function makeFilterServiceFromConn(
    ConnectionInterface $conn,
    ProductListFilterRegistry $filterRegistry,
): CategoryAssignmentService {
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
        filterRegistry: $filterRegistry,
    );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('applies registered filter contributors to the category query before pagination', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(catFilterProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConnectionInterface $conn */
        $conn = $store->container()->get(ConnectionInterface::class);

        $appliedCount = 0;
        $spyFilter = new class ($appliedCount) implements ProductListFilterInterface {
            public function __construct(private int &$appliedCount) {}

            public function apply(
                RepositoryQueryBuilder $repositoryQueryBuilder,
                FilterSelection $filterSelection,
            ): void
            {
                $this->appliedCount++;
            }
        };

        $filterRegistry = new ProductListFilterRegistry();
        $filterRegistry->register($spyFilter);

        $service = makeFilterServiceFromConn($conn, $filterRegistry);

        $categoryFactory = CategoryFactory::new($store);
        $productFactory = ProductFactory::new($store);

        $category = $categoryFactory->withName('Filter Test Category')->create();
        $product = $productFactory->withName('Widget')->withSku('FILT-001')->create();

        $conn->execute(
            'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
            [(int) $product->id, (int) $category->id, 1],
        );

        $selection = new FilterSelection(['color' => ['red']]);
        $service->paginatedProductsInCategory((int) $category->id, makeFilterPositionSortOptions(), $selection);

        expect($appliedCount)->toBe(1);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('leaves the query unchanged when the filter selection is empty', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(catFilterProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConnectionInterface $conn */
        $conn = $store->container()->get(ConnectionInterface::class);

        $appliedCount = 0;
        $noopFilter = new class ($appliedCount) implements ProductListFilterInterface {
            public function __construct(private int &$appliedCount) {}

            public function apply(
                RepositoryQueryBuilder $repositoryQueryBuilder,
                FilterSelection $filterSelection,
            ): void
            {
                // A well-behaved filter does nothing when the selection is empty
                if ($filterSelection->isEmpty()) {
                    return;
                }

                $this->appliedCount++;
            }
        };

        $filterRegistry = new ProductListFilterRegistry();
        $filterRegistry->register($noopFilter);

        $service = makeFilterServiceFromConn($conn, $filterRegistry);

        $categoryFactory = CategoryFactory::new($store);
        $productFactory = ProductFactory::new($store);

        $category = $categoryFactory->withName('Empty Filter Category')->create();
        $product = $productFactory->withName('Gadget')->withSku('NOOP-001')->create();

        $conn->execute(
            'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
            [(int) $product->id, (int) $category->id, 1],
        );

        // Call with explicitly empty FilterSelection
        $page = $service->paginatedProductsInCategory(
            (int) $category->id,
            makeFilterPositionSortOptions(),
            new FilterSelection([]),
        );

        // The filter was called but did nothing (empty selection → early return)
        expect($appliedCount)->toBe(0);
        // The product is still returned
        expect($page->items->toArray())->toHaveCount(1);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('still paginates and sorts as before when no filters are selected', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(catFilterProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConnectionInterface $conn */
        $conn = $store->container()->get(ConnectionInterface::class);

        // Empty registry — no filters registered at all
        $filterRegistry = new ProductListFilterRegistry();
        $service = makeFilterServiceFromConn($conn, $filterRegistry);

        $categoryFactory = CategoryFactory::new($store);
        $productFactory = ProductFactory::new($store);

        $category = $categoryFactory->withName('No Filter Sort Category')->create();
        $product3 = $productFactory->withName('Third Product')->withSku('SORT-003')->create();
        $product1 = $productFactory->withName('First Product')->withSku('SORT-001')->create();
        $product2 = $productFactory->withName('Second Product')->withSku('SORT-002')->create();

        $conn->execute(
            'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
            [(int) $product3->id, (int) $category->id, 30],
        );
        $conn->execute(
            'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
            [(int) $product1->id, (int) $category->id, 10],
        );
        $conn->execute(
            'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
            [(int) $product2->id, (int) $category->id, 20],
        );

        // Default call without 3rd argument (backward compat — uses defaulted empty FilterSelection)
        $page = $service->paginatedProductsInCategory((int) $category->id, makeFilterPositionSortOptions());
        $products = $page->items->toArray();

        expect($products)->toHaveCount(3)
            ->and($products[0]->name)->toBe('First Product')
            ->and($products[1]->name)->toBe('Second Product')
            ->and($products[2]->name)->toBe('Third Product');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('narrows the result set when a filter contributor adds a constraint', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(catFilterProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConnectionInterface $conn */
        $conn = $store->container()->get(ConnectionInterface::class);

        $categoryFactory = CategoryFactory::new($store);
        $productFactory = ProductFactory::new($store);

        $category = $categoryFactory->withName('Narrowing Filter Category')->create();
        $product1 = $productFactory->withName('Product Alpha')->withSku('NARR-001')->create();
        $product2 = $productFactory->withName('Product Beta')->withSku('NARR-002')->create();
        $product3 = $productFactory->withName('Product Gamma')->withSku('NARR-003')->create();

        $conn->execute(
            'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
            [(int) $product1->id, (int) $category->id, 1],
        );
        $conn->execute(
            'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
            [(int) $product2->id, (int) $category->id, 2],
        );
        $conn->execute(
            'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
            [(int) $product3->id, (int) $category->id, 3],
        );

        // A filter that constrains to a single SKU from the selection
        $skuFilter = new class ($product2->id) implements ProductListFilterInterface {
            public function __construct(private readonly int|string|null $allowedProductId) {}

            public function apply(
                RepositoryQueryBuilder $repositoryQueryBuilder,
                FilterSelection $filterSelection,
            ): void
            {
                $skus = $filterSelection->forKey('sku');

                if ($skus === []) {
                    return;
                }

                $repositoryQueryBuilder->whereIn('catalog_products.sku', $skus);
            }
        };

        $filterRegistry = new ProductListFilterRegistry();
        $filterRegistry->register($skuFilter);

        $service = makeFilterServiceFromConn($conn, $filterRegistry);

        // Select only NARR-002 via the sku filter
        $selection = new FilterSelection(['sku' => ['NARR-002']]);
        $page = $service->paginatedProductsInCategory(
            (int) $category->id,
            makeFilterPositionSortOptions(),
            $selection,
        );

        $products = $page->items->toArray();

        expect($products)->toHaveCount(1)
            ->and($products[0]->sku)->toBe('NARR-002');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
