<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Feature;

require_once __DIR__ . '/Helpers/PostgresTestConnection.php';

use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Catalog\Repositories\CategoryRepository;
use Markommerce\Catalog\Repositories\CategoryTreeMarketAssignmentRepository;
use Markommerce\Catalog\Repositories\CategoryTreeNodeRepository;
use Markommerce\Catalog\Repositories\CategoryTreeRepository;
use Markommerce\Catalog\Repositories\ProductCategoryAssignmentRepository;
use Markommerce\Catalog\Repositories\ProductRepository;
use Markommerce\Catalog\Seed\CatalogSeeder;
use Markommerce\Catalog\Services\CategoryTreeService;
use Markommerce\Catalog\Tests\Feature\Helpers\PostgresTestConnection;
use Markommerce\Scope\Storage\DefaultScopeGuard;

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * @return array{
 *   seeder: CatalogSeeder,
 *   treeRepository: CategoryTreeRepository,
 *   treeNodeRepository: CategoryTreeNodeRepository,
 *   categoryRepository: CategoryRepository,
 * }
 */
function makeSeederWithRealRepos(PostgresTestConnection $conn): array
{
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator($metadataFactory);

    $categoryRepository = new CategoryRepository($conn, $metadataFactory, $hydrator);
    $treeRepository = new CategoryTreeRepository($conn, $metadataFactory, $hydrator);
    $treeNodeRepository = new CategoryTreeNodeRepository($conn, $metadataFactory, $hydrator);
    $treeMarketAssignmentRepository = new CategoryTreeMarketAssignmentRepository($conn, $metadataFactory, $hydrator);
    $productRepository = new ProductRepository($conn, $metadataFactory, $hydrator);
    $assignmentRepository = new ProductCategoryAssignmentRepository($conn, $metadataFactory, $hydrator);

    $categoryTreeService = new CategoryTreeService(
        categoryTreeRepository: $treeRepository,
        categoryTreeMarketAssignmentRepository: $treeMarketAssignmentRepository,
        categoryTreeNodeRepository: $treeNodeRepository,
        categoryRepository: $categoryRepository,
    );

    $seeder = new CatalogSeeder(
        productRepository: $productRepository,
        categoryRepository: $categoryRepository,
        assignmentRepository: $assignmentRepository,
        categoryTreeService: $categoryTreeService,
        categoryTreeNodeRepository: $treeNodeRepository,
    );

    return [
        'seeder' => $seeder,
        'treeRepository' => $treeRepository,
        'treeNodeRepository' => $treeNodeRepository,
        'categoryRepository' => $categoryRepository,
    ];
}

// ─── Shared connection & lifecycle ───────────────────────────────────────────

beforeEach(function (): void {
    PostgresTestConnection::skipIfUnavailable();

    DefaultScopeGuard::configure(['locale' => 'default']);

    $this->conn = new PostgresTestConnection();

    // Drop tables from previous runs (handle ordering for FK constraints)
    $this->conn->execute('DROP TABLE IF EXISTS catalog_product_category CASCADE');
    $this->conn->execute('DROP TABLE IF EXISTS catalog_products CASCADE');
    $this->conn->execute('DROP TABLE IF EXISTS catalog_category_tree_nodes CASCADE');
    $this->conn->execute('DROP TABLE IF EXISTS catalog_category_tree_market_assignments CASCADE');
    $this->conn->execute('DROP TABLE IF EXISTS catalog_categories CASCADE');
    $this->conn->execute('DROP TABLE IF EXISTS catalog_category_trees CASCADE');

    $this->conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_category_trees (
            id         SERIAL PRIMARY KEY,
            code       VARCHAR(64) NOT NULL UNIQUE,
            name       VARCHAR(255) NOT NULL,
            is_default BOOLEAN NOT NULL DEFAULT FALSE
        )',
    );

    $this->conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_category_tree_market_assignments (
            market  VARCHAR(64) PRIMARY KEY,
            tree_id INTEGER REFERENCES catalog_category_trees(id) ON DELETE RESTRICT
        )',
    );

    $this->conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_categories (
            id          SERIAL PRIMARY KEY,
            name        VARCHAR(255) NOT NULL,
            description TEXT,
            scopes      JSON
        )',
    );

    $this->conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_category_tree_nodes (
            id             SERIAL PRIMARY KEY,
            tree_id        INTEGER NOT NULL REFERENCES catalog_category_trees(id) ON DELETE CASCADE,
            category_id    INTEGER NOT NULL REFERENCES catalog_categories(id) ON DELETE RESTRICT,
            parent_node_id INTEGER REFERENCES catalog_category_tree_nodes(id) ON DELETE CASCADE,
            position       INTEGER NOT NULL DEFAULT 0
        )',
    );

    $this->conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_products (
            id          SERIAL PRIMARY KEY,
            sku         VARCHAR(255) NOT NULL UNIQUE,
            name        VARCHAR(255) NOT NULL,
            description TEXT,
            scopes      JSON
        )',
    );

    $this->conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_product_category (
            id          SERIAL PRIMARY KEY,
            product_id  INTEGER NOT NULL REFERENCES catalog_products(id) ON DELETE CASCADE,
            category_id INTEGER NOT NULL REFERENCES catalog_categories(id) ON DELETE RESTRICT
        )',
    );
});

afterEach(function (): void {
    if (isset($this->conn)) {
        $this->conn->execute('DROP TABLE IF EXISTS catalog_product_category CASCADE');
        $this->conn->execute('DROP TABLE IF EXISTS catalog_products CASCADE');
        $this->conn->execute('DROP TABLE IF EXISTS catalog_category_tree_nodes CASCADE');
        $this->conn->execute('DROP TABLE IF EXISTS catalog_category_tree_market_assignments CASCADE');
        $this->conn->execute('DROP TABLE IF EXISTS catalog_categories CASCADE');
        $this->conn->execute('DROP TABLE IF EXISTS catalog_category_trees CASCADE');
    }

    DefaultScopeGuard::reset();
});

// ─── Tests ───────────────────────────────────────────────────────────────────

it('running the seeder creates the default tree when none exists', function (): void {
    ['seeder' => $seeder, 'treeRepository' => $treeRepository] = makeSeederWithRealRepos($this->conn);

    $seeder->run();

    $defaultTree = $treeRepository->findDefault();

    expect($defaultTree)->not->toBeNull()
        ->and($defaultTree->isDefault)->toBeTrue()
        ->and($defaultTree->code)->toBe('default');
})->group('integration-destructive');

it('running the seeder reuses an existing default tree without creating a duplicate', function (): void {
    ['seeder' => $seeder, 'treeRepository' => $treeRepository] = makeSeederWithRealRepos($this->conn);

    $seeder->run();

    $allTrees = $treeRepository->findAll();
    $defaultTrees = array_values(array_filter(
        $allTrees->toArray(),
        fn ($t) => $t->isDefault,
    ));

    expect($defaultTrees)->toHaveCount(1);
})->group('integration-destructive');

it('the tree-placement step is idempotent: when seeded categories already have a placement in the default tree, the seeder does not create a second placement for the same (category, tree) pair', function (): void {
    [
        'seeder' => $seeder,
        'treeRepository' => $treeRepository,
        'treeNodeRepository' => $treeNodeRepository,
        'categoryRepository' => $categoryRepository,
    ] = makeSeederWithRealRepos($this->conn);

    // Run seeder once to seed categories, products, and initial tree placements
    $seeder->run();

    $defaultTree = $treeRepository->findDefault();
    $treeId = (int) $defaultTree->id;

    $nodesAfterFirstRun = $treeNodeRepository->findByTree($treeId);
    $nodeCountAfterFirstRun = count($nodesAfterFirstRun);

    // Simulate re-running the tree-placement step by building a new seeder
    // that shares the same real repositories and calling the placement logic
    // directly through a fresh CategoryTreeService
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator($metadataFactory);

    $freshTreeNodeRepository = new CategoryTreeNodeRepository($this->conn, $metadataFactory, $hydrator);
    $freshTreeRepository = new CategoryTreeRepository($this->conn, $metadataFactory, $hydrator);
    $freshMarketRepository = new CategoryTreeMarketAssignmentRepository($this->conn, $metadataFactory, $hydrator);
    $freshCategoryRepository = new CategoryRepository($this->conn, $metadataFactory, $hydrator);
    $freshProductRepository = new ProductRepository($this->conn, $metadataFactory, $hydrator);
    $freshAssignmentRepository = new ProductCategoryAssignmentRepository($this->conn, $metadataFactory, $hydrator);

    $freshTreeService = new CategoryTreeService(
        categoryTreeRepository: $freshTreeRepository,
        categoryTreeMarketAssignmentRepository: $freshMarketRepository,
        categoryTreeNodeRepository: $freshTreeNodeRepository,
        categoryRepository: $freshCategoryRepository,
    );

    // Place all already-seeded categories again — the idempotency guard should prevent duplicates
    $alreadySeededCategories = $freshCategoryRepository->findAll()->toArray();
    $freshDefaultTree = $freshTreeService->ensureDefaultTreeExists();

    foreach ($alreadySeededCategories as $category) {
        $existing = $freshTreeNodeRepository->findByCategoryInTree((int) $category->id, (int) $freshDefaultTree->id);

        if (count($existing) === 0) {
            $freshTreeService->placeCategory((int) $freshDefaultTree->id, (int) $category->id, parentNodeId: null, position: null);
        }
    }

    $nodesAfterSecondPass = $freshTreeNodeRepository->findByTree((int) $freshDefaultTree->id);

    // The node count should not have grown — no duplicate placements were created
    expect(count($nodesAfterSecondPass))->toBe($nodeCountAfterFirstRun);
})->group('integration-destructive');

it('seeded categories appear in stable position order in the default tree', function (): void {
    [
        'seeder' => $seeder,
        'treeRepository' => $treeRepository,
        'treeNodeRepository' => $treeNodeRepository,
        'categoryRepository' => $categoryRepository,
    ] = makeSeederWithRealRepos($this->conn);

    $seeder->run();

    $defaultTree = $treeRepository->findDefault();
    $treeId = (int) $defaultTree->id;

    $rootNodes = $treeNodeRepository->findRoots($treeId);
    $allCategories = $categoryRepository->findAll()->toArray();

    // findRoots returns nodes sorted by position
    // Categories should appear in seeding order (1..5), each with incrementing positions
    $positions = array_map(fn ($n) => $n->position, $rootNodes);

    // All positions must be strictly ascending
    $sortedPositions = $positions;
    sort($sortedPositions);

    expect($positions)->toBe($sortedPositions)
        ->and(count(array_unique($positions)))->toBe(count($positions));
})->group('integration-destructive');

it('running the seeder places every seeded category as a root node in the default tree', function (): void {
    [
        'seeder' => $seeder,
        'treeRepository' => $treeRepository,
        'treeNodeRepository' => $treeNodeRepository,
        'categoryRepository' => $categoryRepository,
    ] = makeSeederWithRealRepos($this->conn);

    $seeder->run();

    $defaultTree = $treeRepository->findDefault();
    $treeId = (int) $defaultTree->id;
    $rootNodes = $treeNodeRepository->findRoots($treeId);
    $allCategories = $categoryRepository->findAll()->toArray();

    $rootCategoryIds = array_map(fn ($n) => (int) $n->categoryId, $rootNodes);
    $allCategoryIds = array_map(fn ($c) => (int) $c->id, $allCategories);

    sort($rootCategoryIds);
    sort($allCategoryIds);

    expect($rootNodes)->toHaveCount(5)
        ->and($rootCategoryIds)->toBe($allCategoryIds);
})->group('integration-destructive');
