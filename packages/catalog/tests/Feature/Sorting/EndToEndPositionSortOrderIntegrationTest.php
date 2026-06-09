<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Feature\Sorting;

use Marko\Database\Connection\ConnectionInterface;
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
use Markommerce\Catalog\Tests\Support\CategoryFactory;
use Markommerce\Catalog\Tests\Support\ProductFactory;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;
use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Criteria\Position\PositionCodec;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Strategy\KeysetPaginationStrategy;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function e2ePositionVendorDir(): string
{
    return dirname(__DIR__, 5) . '/vendor';
}

function e2ePositionMakeServiceFromConn(ConnectionInterface $conn): CategoryAssignmentService
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

// ─── Tests ───────────────────────────────────────────────────────────────────

it('lists category products in assignment position order by default', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(StoreProfile::simple(e2ePositionVendorDir()));
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConnectionInterface $conn */
        $conn = $store->container()->get(ConnectionInterface::class);

        $registry = e2ePositionMakeRegistry();
        $resolver = e2ePositionMakeResolver($registry);
        $service  = e2ePositionMakeServiceFromConn($conn);

        $categoryFactory = CategoryFactory::new($store);
        $productFactory  = ProductFactory::new($store);

        $category = $categoryFactory->withName('Default Sort Category')->create();
        $product3 = $productFactory->withName('Third')->withSku('E2E-POS-003')->create();
        $product1 = $productFactory->withName('First')->withSku('E2E-POS-001')->create();
        $product2 = $productFactory->withName('Second')->withSku('E2E-POS-002')->create();

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

        // Drive through the real resolve → apply path (no sort param = default position)
        $options  = $resolver->resolve(page: 1, size: 10, sort: null);
        $page     = $service->paginatedProductsInCategory((int) $category->id, $options);
        $products = $page->items->toArray();

        expect($products)->toHaveCount(3)
            ->and($products[0]->id)->toBe($product1->id)
            ->and($products[1]->id)->toBe($product2->id)
            ->and($products[2]->id)->toBe($product3->id);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
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
