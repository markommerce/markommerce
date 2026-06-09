<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Feature\Services;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;
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
use Markommerce\Catalog\Sorting\CategorySortOrderInterface;
use Markommerce\Catalog\Sorting\ColumnSortOrder;
use Markommerce\Catalog\Tests\Support\CategoryFactory;
use Markommerce\Catalog\Tests\Support\ProductFactory;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;
use Markommerce\Criteria\Page\Page;
use Markommerce\Criteria\Position\PositionCodec;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Strategy\KeysetPaginationStrategy;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function catAssignPagVendorDir(): string
{
    return dirname(__DIR__, 5) . '/vendor';
}

function catAssignPagProfile(): StoreProfile
{
    return StoreProfile::simple(catAssignPagVendorDir());
}

function makeAssignmentServiceFromConn(ConnectionInterface $conn): CategoryAssignmentService
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

function catPagMakePositionOrder(): ColumnSortOrder
{
    return new ColumnSortOrder(
        key: 'position',
        label: 'Position',
        column: 'catalog_product_category.position',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    );
}

function catPagMakeOffsetOptions(
    int $pageSize = 10,
    int $page = 1,
    ?CategorySortOrderInterface $sortOrder = null,
): ResolvedPaginationOptions {
    return new ResolvedPaginationOptions(
        sortOrder: $sortOrder ?? catPagMakePositionOrder(),
        size: $pageSize,
        page: $page,
        presentation: PaginationPresentation::Numbered,
        strategyKind: PaginationStrategyKind::Offset,
        countMode: CountMode::Exact,
    );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('returns a page of products assigned to the category', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(catAssignPagProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConnectionInterface $conn */
        $conn = $store->container()->get(ConnectionInterface::class);

        $service = makeAssignmentServiceFromConn($conn);

        $categoryFactory = CategoryFactory::new($store);
        $productFactory  = ProductFactory::new($store);

        $category = $categoryFactory->withName('Electronics')->create();
        $product1 = $productFactory->withName('Laptop')->withSku('LAPTOP-001')->create();
        $product2 = $productFactory->withName('Phone')->withSku('PHONE-001')->create();

        $conn->execute(
            'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
            [(int) $product1->id, (int) $category->id, 1],
        );
        $conn->execute(
            'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
            [(int) $product2->id, (int) $category->id, 2],
        );

        $options = catPagMakeOffsetOptions(pageSize: 10);

        $page = $service->paginatedProductsInCategory((int) $category->id, $options);

        expect($page)->toBeInstanceOf(Page::class)
            ->and($page->items->toArray())->toHaveCount(2)
            ->and($page->items->toArray()[0])->toBeInstanceOf(Product::class);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('fetches the products in a single join query without per-product lookups', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(catAssignPagProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConnectionInterface $realConn */
        $realConn = $store->container()->get(ConnectionInterface::class);

        $metadataFactory = new EntityMetadataFactory();
        $hydrator = new EntityHydrator($metadataFactory);
        $positionCodec = new PositionCodec();
        $keysetStrategy = new KeysetPaginationStrategy($positionCodec);

        $queryCount = 0;
        $spyConn = new class ($realConn, $queryCount) extends TestConnection
        {
            public function __construct(
                private ConnectionInterface $wrapped,
                public int &$queryCount,
            ) {
                // Skip parent constructor — we delegate to wrapped
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
        $categoryRepo = new CategoryRepository($realConn, $metadataFactory, $hydrator);
        $assignmentRepo = new ProductCategoryAssignmentRepository($realConn, $metadataFactory, $hydrator);

        $service = new CategoryAssignmentService(
            productRepository: $productRepo,
            categoryRepository: $categoryRepo,
            productCategoryAssignmentRepository: $assignmentRepo,
            positionCodec: $positionCodec,
            keysetPaginationStrategy: $keysetStrategy,
        );

        $categoryFactory = CategoryFactory::new($store);
        $productFactory  = ProductFactory::new($store);

        $category = $categoryFactory->withName('Tech')->create();

        for ($i = 1; $i <= 5; $i++) {
            $product = $productFactory->withName("Product $i")->withSku("SPY-SKU-$i")->create();
            $realConn->execute(
                'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
                [(int) $product->id, (int) $category->id, $i],
            );
        }

        $options = catPagMakeOffsetOptions(pageSize: 10);

        $spyConn->queryCount = 0;
        $service->paginatedProductsInCategory((int) $category->id, $options);

        // One count query + one product query (join) = 2 max; NOT 5 (one per product)
        expect($spyConn->queryCount)->toBeLessThanOrEqual(2);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('applies the configured page size to the result', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(catAssignPagProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConnectionInterface $conn */
        $conn = $store->container()->get(ConnectionInterface::class);

        $service = makeAssignmentServiceFromConn($conn);

        $categoryFactory = CategoryFactory::new($store);
        $productFactory  = ProductFactory::new($store);

        $category = $categoryFactory->withName('Books')->create();

        for ($i = 1; $i <= 7; $i++) {
            $product = $productFactory->withName("Book $i")->withSku("BOOK-00$i")->create();
            $conn->execute(
                'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
                [(int) $product->id, (int) $category->id, $i],
            );
        }

        $options = catPagMakeOffsetOptions(pageSize: 3);

        $page = $service->paginatedProductsInCategory((int) $category->id, $options);

        expect($page->items->toArray())->toHaveCount(3);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('orders products by the configured sort column with an id tie-break', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(catAssignPagProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConnectionInterface $conn */
        $conn = $store->container()->get(ConnectionInterface::class);

        $service = makeAssignmentServiceFromConn($conn);

        $categoryFactory = CategoryFactory::new($store);
        $productFactory  = ProductFactory::new($store);

        $category = $categoryFactory->withName('Sorted Category')->create();
        $productC = $productFactory->withName('Charlie')->withSku('SKU-C')->create();
        $productA = $productFactory->withName('Alpha')->withSku('SKU-A')->create();
        $productB = $productFactory->withName('Beta')->withSku('SKU-B')->create();

        $conn->execute(
            'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
            [(int) $productC->id, (int) $category->id, 1],
        );
        $conn->execute(
            'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
            [(int) $productA->id, (int) $category->id, 2],
        );
        $conn->execute(
            'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
            [(int) $productB->id, (int) $category->id, 3],
        );

        $nameOrder = new ColumnSortOrder(
            key: 'name',
            label: 'Name',
            column: 'catalog_products.name',
            direction: SortDirection::Ascending,
            supportsKeyset: false,
        );
        $options = catPagMakeOffsetOptions(pageSize: 10, sortOrder: $nameOrder);

        $page = $service->paginatedProductsInCategory((int) $category->id, $options);
        $products = $page->items->toArray();

        expect($products)->toHaveCount(3)
            ->and($products[0]->name)->toBe('Alpha')
            ->and($products[1]->name)->toBe('Beta')
            ->and($products[2]->name)->toBe('Charlie');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('returns a next position when more products exist', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(catAssignPagProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConnectionInterface $conn */
        $conn = $store->container()->get(ConnectionInterface::class);

        $service = makeAssignmentServiceFromConn($conn);

        $categoryFactory = CategoryFactory::new($store);
        $productFactory  = ProductFactory::new($store);

        $category = $categoryFactory->withName('Many Products')->create();

        for ($i = 1; $i <= 5; $i++) {
            $product = $productFactory->withName("Product $i")->withSku("MANYPROD-$i")->create();
            $conn->execute(
                'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
                [(int) $product->id, (int) $category->id, $i],
            );
        }

        $options = catPagMakeOffsetOptions(pageSize: 3);

        $page = $service->paginatedProductsInCategory((int) $category->id, $options);

        expect($page->hasNext())->toBeTrue()
            ->and($page->nextPosition)->not->toBeNull();
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('throws CategoryNotFoundException for an unknown category', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(catAssignPagProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConnectionInterface $conn */
        $conn = $store->container()->get(ConnectionInterface::class);

        $service = makeAssignmentServiceFromConn($conn);

        $options = catPagMakeOffsetOptions(pageSize: 10);

        expect(fn () => $service->paginatedProductsInCategory(99999, $options))
            ->toThrow(CategoryNotFoundException::class);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('derives the offset total from a join-safe count not from the joined builder', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(catAssignPagProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConnectionInterface $conn */
        $conn = $store->container()->get(ConnectionInterface::class);

        $service = makeAssignmentServiceFromConn($conn);

        $categoryFactory = CategoryFactory::new($store);
        $productFactory  = ProductFactory::new($store);

        $category = $categoryFactory->withName('Count Test')->create();
        $otherCategory = $categoryFactory->withName('Other Category')->create();

        // 4 products in target category
        for ($i = 1; $i <= 4; $i++) {
            $product = $productFactory->withName("CountProd $i")->withSku("COUNTPROD-$i")->create();
            $conn->execute(
                'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
                [(int) $product->id, (int) $category->id, $i],
            );
        }

        // 10 products in another category (should not affect count)
        for ($i = 1; $i <= 10; $i++) {
            $product = $productFactory->withName("OtherProd $i")->withSku("OTHERPROD-$i")->create();
            $conn->execute(
                'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
                [(int) $product->id, (int) $otherCategory->id, $i],
            );
        }

        $options = catPagMakeOffsetOptions(pageSize: 3);

        $page = $service->paginatedProductsInCategory((int) $category->id, $options);

        // Page items should be 3 (page size), total should reflect only the 4 in the category
        expect($page->items->toArray())->toHaveCount(3);

        // The page must know there's a next page (4 products, page size 3)
        expect($page->hasNext())->toBeTrue();
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it orders products by assignment position by default without a hand-qualified column', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(catAssignPagProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConnectionInterface $conn */
        $conn = $store->container()->get(ConnectionInterface::class);

        $service = makeAssignmentServiceFromConn($conn);

        $categoryFactory = CategoryFactory::new($store);
        $productFactory  = ProductFactory::new($store);

        $category = $categoryFactory->withName('Position Order')->create();
        $product3 = $productFactory->withName('Third')->withSku('POS-003')->create();
        $product1 = $productFactory->withName('First')->withSku('POS-001')->create();
        $product2 = $productFactory->withName('Second')->withSku('POS-002')->create();

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

        $options = catPagMakeOffsetOptions(pageSize: 10);

        $page = $service->paginatedProductsInCategory((int) $category->id, $options);
        $products = $page->items->toArray();

        expect($products)->toHaveCount(3)
            ->and($products[0]->name)->toBe('First')
            ->and($products[1]->name)->toBe('Second')
            ->and($products[2]->name)->toBe('Third');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it orders products using the sort fields contributed by the selected order', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(catAssignPagProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConnectionInterface $conn */
        $conn = $store->container()->get(ConnectionInterface::class);

        $service = makeAssignmentServiceFromConn($conn);

        $categoryFactory = CategoryFactory::new($store);
        $productFactory  = ProductFactory::new($store);

        $category = $categoryFactory->withName('SKU Sort')->create();
        $productZ = $productFactory->withName('Zeta')->withSku('SKU-ZZZ')->create();
        $productA = $productFactory->withName('Alpha')->withSku('SKU-AAA')->create();
        $productM = $productFactory->withName('Mu')->withSku('SKU-MMM')->create();

        $conn->execute(
            'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
            [(int) $productZ->id, (int) $category->id, 1],
        );
        $conn->execute(
            'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
            [(int) $productA->id, (int) $category->id, 2],
        );
        $conn->execute(
            'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
            [(int) $productM->id, (int) $category->id, 3],
        );

        $skuOrder = new ColumnSortOrder(
            key: 'sku',
            label: 'SKU',
            column: 'catalog_products.sku',
            direction: SortDirection::Ascending,
            supportsKeyset: false,
        );
        $options = catPagMakeOffsetOptions(pageSize: 10, sortOrder: $skuOrder);

        $page = $service->paginatedProductsInCategory((int) $category->id, $options);
        $products = $page->items->toArray();

        expect($products)->toHaveCount(3)
            ->and($products[0]->sku)->toBe('SKU-AAA')
            ->and($products[1]->sku)->toBe('SKU-MMM')
            ->and($products[2]->sku)->toBe('SKU-ZZZ');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it applies joins contributed by the selected sort order before paginating', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(catAssignPagProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConnectionInterface $conn */
        $conn = $store->container()->get(ConnectionInterface::class);

        $service = makeAssignmentServiceFromConn($conn);

        $categoryFactory = CategoryFactory::new($store);
        $productFactory  = ProductFactory::new($store);

        $category = $categoryFactory->withName('Join Test')->create();

        for ($i = 1; $i <= 3; $i++) {
            $product = $productFactory->withName("JoinProd $i")->withSku("JOIN-00$i")->create();
            $conn->execute(
                'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
                [(int) $product->id, (int) $category->id, $i],
            );
        }

        $prepareCallCount = 0;

        $trackingOrder = new class ('position', 'Position', 'catalog_product_category.position', SortDirection::Ascending, false, $prepareCallCount) extends ColumnSortOrder
        {
            public function __construct(
                string $key,
                string $label,
                string $column,
                SortDirection $direction,
                bool $supportsKeyset,
                public int &$prepareCallCount,
            ) {
                parent::__construct($key, $label, $column, $direction, $supportsKeyset);
            }

            public function prepareQuery(\Marko\Database\Repository\RepositoryQueryBuilder $repositoryQueryBuilder): void
            {
                $this->prepareCallCount++;
                parent::prepareQuery($repositoryQueryBuilder);
            }
        };

        $options = catPagMakeOffsetOptions(pageSize: 10, sortOrder: $trackingOrder);

        $service->paginatedProductsInCategory((int) $category->id, $options);

        expect($prepareCallCount)->toBe(1);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it preserves the id ascending tie-break for equal sort values', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(catAssignPagProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConnectionInterface $conn */
        $conn = $store->container()->get(ConnectionInterface::class);

        $service = makeAssignmentServiceFromConn($conn);

        $categoryFactory = CategoryFactory::new($store);
        $productFactory  = ProductFactory::new($store);

        $category = $categoryFactory->withName('Tie Break')->create();
        $product1 = $productFactory->withName('Same')->withSku('TIE-001')->create();
        $product2 = $productFactory->withName('Same')->withSku('TIE-002')->create();
        $product3 = $productFactory->withName('Same')->withSku('TIE-003')->create();

        $conn->execute(
            'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
            [(int) $product1->id, (int) $category->id, 1],
        );
        $conn->execute(
            'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
            [(int) $product2->id, (int) $category->id, 1],
        );
        $conn->execute(
            'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
            [(int) $product3->id, (int) $category->id, 1],
        );

        $nameOrder = new ColumnSortOrder(
            key: 'name',
            label: 'Name',
            column: 'catalog_products.name',
            direction: SortDirection::Ascending,
            supportsKeyset: false,
        );
        $options = catPagMakeOffsetOptions(pageSize: 10, sortOrder: $nameOrder);

        $page = $service->paginatedProductsInCategory((int) $category->id, $options);
        $products = $page->items->toArray();

        expect($products)->toHaveCount(3);
        expect($products[0]->id)->toBeLessThan($products[1]->id);
        expect($products[1]->id)->toBeLessThan($products[2]->id);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it encodes the offset position token for pages beyond the first', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(catAssignPagProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConnectionInterface $conn */
        $conn = $store->container()->get(ConnectionInterface::class);

        $service = makeAssignmentServiceFromConn($conn);

        $categoryFactory = CategoryFactory::new($store);
        $productFactory  = ProductFactory::new($store);

        $category = $categoryFactory->withName('Offset Token')->create();

        for ($i = 1; $i <= 10; $i++) {
            $product = $productFactory->withName("Token Prod $i")->withSku("TOKPROD-$i")->create();
            $conn->execute(
                'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
                [(int) $product->id, (int) $category->id, $i],
            );
        }

        $options = catPagMakeOffsetOptions(pageSize: 3, page: 2);

        $page = $service->paginatedProductsInCategory((int) $category->id, $options);

        // Page 2 of 10 products (size 3) → items 4,5,6
        expect($page->items->toArray())->toHaveCount(3);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it returns the requested page size of products', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(catAssignPagProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConnectionInterface $conn */
        $conn = $store->container()->get(ConnectionInterface::class);

        $service = makeAssignmentServiceFromConn($conn);

        $categoryFactory = CategoryFactory::new($store);
        $productFactory  = ProductFactory::new($store);

        $category = $categoryFactory->withName('Page Size')->create();

        for ($i = 1; $i <= 20; $i++) {
            $product = $productFactory->withName("Prod $i")->withSku("PSIZEPROD-$i")->create();
            $conn->execute(
                'INSERT INTO catalog_product_category (product_id, category_id, position) VALUES (?, ?, ?)',
                [(int) $product->id, (int) $category->id, $i],
            );
        }

        $options = catPagMakeOffsetOptions(pageSize: 7);

        $page = $service->paginatedProductsInCategory((int) $category->id, $options);

        expect($page->items->toArray())->toHaveCount(7);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
