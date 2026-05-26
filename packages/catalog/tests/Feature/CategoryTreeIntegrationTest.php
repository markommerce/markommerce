<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Feature;

require_once __DIR__ . '/Helpers/PostgresTestConnection.php';

use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Exceptions\CategoryHasPlacementsException;
use Markommerce\Catalog\Exceptions\CircularNodeReferenceException;
use Markommerce\Catalog\Repositories\CategoryRepository;
use Markommerce\Catalog\Repositories\CategoryTreeMarketAssignmentRepository;
use Markommerce\Catalog\Repositories\CategoryTreeNodeRepository;
use Markommerce\Catalog\Repositories\CategoryTreeRepository;
use Markommerce\Catalog\Services\CategoryService;
use Markommerce\Catalog\Services\CategoryTreeService;
use Markommerce\Catalog\Tests\Feature\Helpers\PostgresTestConnection;
use Markommerce\Scope\Storage\DefaultScopeGuard;

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * @return array{
 *   treeService: CategoryTreeService,
 *   categoryService: CategoryService,
 *   categoryRepository: CategoryRepository,
 *   treeNodeRepository: CategoryTreeNodeRepository,
 *   treeRepository: CategoryTreeRepository,
 * }
 */
function makeServices(PostgresTestConnection $conn): array
{
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator($metadataFactory);

    $categoryRepository = new CategoryRepository($conn, $metadataFactory, $hydrator);
    $treeRepository = new CategoryTreeRepository($conn, $metadataFactory, $hydrator);
    $treeNodeRepository = new CategoryTreeNodeRepository($conn, $metadataFactory, $hydrator);
    $treeMarketAssignmentRepository = new CategoryTreeMarketAssignmentRepository($conn, $metadataFactory, $hydrator);

    $treeService = new CategoryTreeService(
        categoryTreeRepository: $treeRepository,
        categoryTreeMarketAssignmentRepository: $treeMarketAssignmentRepository,
        categoryTreeNodeRepository: $treeNodeRepository,
        categoryRepository: $categoryRepository,
    );

    $categoryService = new CategoryService(
        categoryRepository: $categoryRepository,
        categoryTreeNodeRepository: $treeNodeRepository,
    );

    return [
        'treeService' => $treeService,
        'categoryService' => $categoryService,
        'categoryRepository' => $categoryRepository,
        'treeNodeRepository' => $treeNodeRepository,
        'treeRepository' => $treeRepository,
    ];
}

function makeCategory(CategoryRepository $categoryRepository, string $name): Category
{
    $category = new Category();
    $category->name = $name;
    $categoryRepository->save($category);

    return $category;
}

// ─── Shared connection & lifecycle ───────────────────────────────────────────

beforeEach(function (): void {
    PostgresTestConnection::skipIfUnavailable();

    DefaultScopeGuard::configure(['locale' => 'default']);

    $this->conn = new PostgresTestConnection();

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
});

afterEach(function (): void {
    if (isset($this->conn)) {
        $this->conn->execute('DROP TABLE IF EXISTS catalog_category_tree_nodes CASCADE');
        $this->conn->execute('DROP TABLE IF EXISTS catalog_category_tree_market_assignments CASCADE');
        $this->conn->execute('DROP TABLE IF EXISTS catalog_categories CASCADE');
        $this->conn->execute('DROP TABLE IF EXISTS catalog_category_trees CASCADE');
    }

    DefaultScopeGuard::reset();
});

// ─── Tests ───────────────────────────────────────────────────────────────────

it('creates a non-default tree, places categories, assigns it to a market, and resolves the tree for that market', function (): void {
    [
        'treeService' => $treeService,
        'categoryRepository' => $categoryRepository,
    ] = makeServices($this->conn);

    // Create a non-default tree
    $nonDefaultTree = $treeService->createTree('uk', 'UK Tree', false);
    expect($nonDefaultTree->id)->not->toBeNull();

    // Create and place categories in the tree
    $catA = makeCategory($categoryRepository, 'Category A');
    $catB = makeCategory($categoryRepository, 'Category B');

    $nodeA = $treeService->placeCategory((int) $nonDefaultTree->id, (int) $catA->id);
    $nodeB = $treeService->placeCategory((int) $nonDefaultTree->id, (int) $catB->id);

    expect($nodeA->id)->not->toBeNull()
        ->and($nodeA->treeId)->toBe($nonDefaultTree->id)
        ->and($nodeA->categoryId)->toBe($catA->id)
        ->and($nodeB->id)->not->toBeNull()
        ->and($nodeB->categoryId)->toBe($catB->id);

    // Assign the tree to a market
    $treeService->assignTreeToMarket((int) $nonDefaultTree->id, 'uk');

    // Resolve the tree for that market — should return the non-default tree
    $resolved = $treeService->resolveTreeForMarket('uk');

    expect($resolved->id)->toBe($nonDefaultTree->id)
        ->and($resolved->code)->toBe('uk');
})->group('integration-destructive');

it('resolves the default tree for a market with no assignment', function (): void {
    [
        'treeService' => $treeService,
    ] = makeServices($this->conn);

    // Create a default tree
    $defaultTree = $treeService->createTree('default', 'Default Tree', true);

    // Create a non-default tree but do NOT assign it to the market
    $treeService->createTree('de', 'DE Tree', false);

    // Resolving for any market with no assignment should return the default tree
    $resolved = $treeService->resolveTreeForMarket('us');

    expect($resolved->id)->toBe($defaultTree->id)
        ->and($resolved->isDefault)->toBeTrue();
})->group('integration-destructive');

it('materializes the tree with correct nesting and position order against the real database', function (): void {
    [
        'treeService' => $treeService,
        'categoryRepository' => $categoryRepository,
    ] = makeServices($this->conn);

    $tree = $treeService->createTree('main', 'Main Tree', true);
    $treeId = (int) $tree->id;

    $root = makeCategory($categoryRepository, 'Root');
    $child1 = makeCategory($categoryRepository, 'Child 1');
    $child2 = makeCategory($categoryRepository, 'Child 2');
    $grandchild = makeCategory($categoryRepository, 'Grandchild');

    $rootNode = $treeService->placeCategory($treeId, (int) $root->id, parentNodeId: null, position: 0);
    $childNode2 = $treeService->placeCategory($treeId, (int) $child2->id, parentNodeId: (int) $rootNode->id, position: 20);
    $childNode1 = $treeService->placeCategory($treeId, (int) $child1->id, parentNodeId: (int) $rootNode->id, position: 10);
    $grandchildNode = $treeService->placeCategory($treeId, (int) $grandchild->id, parentNodeId: (int) $childNode1->id, position: 0);

    $materialized = $treeService->getMaterializedTree($treeId);

    // One root node
    expect($materialized)->toHaveCount(1);

    $rootEntry = $materialized[0];
    expect($rootEntry['category_id'])->toBe((int) $root->id)
        ->and($rootEntry['children'])->toHaveCount(2);

    // Children should be ordered by position (10, 20)
    $firstChild = $rootEntry['children'][0];
    $secondChild = $rootEntry['children'][1];

    expect($firstChild['category_id'])->toBe((int) $child1->id)
        ->and($secondChild['category_id'])->toBe((int) $child2->id)
        ->and($firstChild['children'])->toHaveCount(1)
        ->and($secondChild['children'])->toHaveCount(0);

    // Grandchild under child1
    $grandchildEntry = $firstChild['children'][0];
    expect($grandchildEntry['category_id'])->toBe((int) $grandchild->id)
        ->and($grandchildEntry['children'])->toHaveCount(0);
})->group('integration-destructive');

it('permits the same category to be placed twice within one tree (multi-placement)', function (): void {
    [
        'treeService' => $treeService,
        'categoryRepository' => $categoryRepository,
    ] = makeServices($this->conn);

    $tree = $treeService->createTree('multi', 'Multi Tree', true);
    $treeId = (int) $tree->id;

    $cat = makeCategory($categoryRepository, 'Shared Category');
    $categoryId = (int) $cat->id;

    // Place the same category twice in the same tree
    $node1 = $treeService->placeCategory($treeId, $categoryId, parentNodeId: null, position: 0);
    $node2 = $treeService->placeCategory($treeId, $categoryId, parentNodeId: null, position: 10);

    expect($node1->id)->not->toBeNull()
        ->and($node2->id)->not->toBeNull()
        ->and($node1->id)->not->toBe($node2->id)
        ->and($node1->categoryId)->toBe($categoryId)
        ->and($node2->categoryId)->toBe($categoryId);
})->group('integration-destructive');

it('prevents deleting a category that has placements (CategoryHasPlacementsException)', function (): void {
    [
        'treeService' => $treeService,
        'categoryService' => $categoryService,
        'categoryRepository' => $categoryRepository,
    ] = makeServices($this->conn);

    $tree = $treeService->createTree('default', 'Default Tree', true);
    $treeId = (int) $tree->id;

    $cat = makeCategory($categoryRepository, 'Placed Category');
    $categoryId = (int) $cat->id;

    $treeService->placeCategory($treeId, $categoryId);

    expect(fn () => $categoryService->delete($categoryId))
        ->toThrow(CategoryHasPlacementsException::class);
})->group('integration-destructive');

it('detects a cycle when attempting to move a node under its own descendant', function (): void {
    [
        'treeService' => $treeService,
        'categoryRepository' => $categoryRepository,
    ] = makeServices($this->conn);

    $tree = $treeService->createTree('cycle-test', 'Cycle Test Tree', true);
    $treeId = (int) $tree->id;

    $catRoot = makeCategory($categoryRepository, 'Root');
    $catChild = makeCategory($categoryRepository, 'Child');
    $catGrandchild = makeCategory($categoryRepository, 'Grandchild');

    $rootNode = $treeService->placeCategory($treeId, (int) $catRoot->id, parentNodeId: null, position: 0);
    $childNode = $treeService->placeCategory($treeId, (int) $catChild->id, parentNodeId: (int) $rootNode->id, position: 0);
    $grandchildNode = $treeService->placeCategory($treeId, (int) $catGrandchild->id, parentNodeId: (int) $childNode->id, position: 0);

    // Moving the root node under its own grandchild should throw a cycle exception
    expect(fn () => $treeService->moveNode((int) $rootNode->id, newParentNodeId: (int) $grandchildNode->id, position: 0))
        ->toThrow(CircularNodeReferenceException::class);
})->group('integration-destructive');
