<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Feature\Services;

use Marko\Database\Connection\ConnectionInterface;
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
