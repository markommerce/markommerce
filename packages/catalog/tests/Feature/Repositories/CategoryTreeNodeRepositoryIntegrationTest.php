<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Feature\Repositories;

require_once __DIR__ . '/../Helpers/PostgresTestConnection.php';

use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Entity\CategoryTreeNode;
use Markommerce\Catalog\Repositories\CategoryTreeNodeRepository;
use Markommerce\Catalog\Repositories\CategoryTreeRepository;
use Markommerce\Catalog\Tests\Feature\Helpers\PostgresTestConnection;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeCategoryTreeNodeTestCategory(string $name): Category
{
    $category = new Category();
    $category->name = $name;

    return $category;
}

function makeTestNode(
    int $treeId,
    int $categoryId,
    ?int $parentNodeId = null,
    int $position = 0,
): CategoryTreeNode {
    $node = new CategoryTreeNode();
    $node->treeId = $treeId;
    $node->categoryId = $categoryId;
    $node->parentNodeId = $parentNodeId;
    $node->position = $position;

    return $node;
}

// ─── Shared connection & lifecycle ───────────────────────────────────────────

beforeEach(function (): void {
    PostgresTestConnection::skipIfUnavailable();

    $this->conn = new PostgresTestConnection();

    $this->conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_category_trees (
            id         SERIAL PRIMARY KEY,
            code       VARCHAR(64) NOT NULL UNIQUE,
            name       VARCHAR(255) NOT NULL,
            is_default BOOLEAN NOT NULL DEFAULT FALSE
        )',
    );

    $this->conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_categories (
            id          SERIAL PRIMARY KEY,
            name        VARCHAR(255) NOT NULL,
            description TEXT
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

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator($metadataFactory);

    $treeRepository = new CategoryTreeRepository($this->conn, $metadataFactory, $hydrator);
    $this->nodeRepository = new CategoryTreeNodeRepository($this->conn, $metadataFactory, $hydrator);

    // Create a tree for FK purposes
    $tree = new CategoryTree();
    $tree->code = 'test-tree';
    $tree->name = 'Test Tree';
    $treeRepository->save($tree);
    $this->treeId = $tree->id;

    // Create a second tree
    $tree2 = new CategoryTree();
    $tree2->code = 'other-tree';
    $tree2->name = 'Other Tree';
    $treeRepository->save($tree2);
    $this->treeId2 = $tree2->id;

    // Insert categories directly for FK references
    $this->conn->execute("INSERT INTO catalog_categories (name) VALUES ('Cat A'), ('Cat B'), ('Cat C')");
    $result = $this->conn->query('SELECT id FROM catalog_categories ORDER BY id ASC');
    $this->catAId = (int) $result[0]['id'];
    $this->catBId = (int) $result[1]['id'];
    $this->catCId = (int) $result[2]['id'];
});

afterEach(function (): void {
    if (isset($this->conn)) {
        $this->conn->execute('DELETE FROM catalog_category_tree_nodes');
        $this->conn->execute('DELETE FROM catalog_categories');
        $this->conn->execute('DELETE FROM catalog_category_trees');
    }
});

// ─── Tests ───────────────────────────────────────────────────────────────────

it('persists a node and reads it back by id', function (): void {
    /** @var CategoryTreeNodeRepository $repository */
    $repository = $this->nodeRepository;

    $node = makeTestNode($this->treeId, $this->catAId, null, 1);
    $repository->save($node);

    expect($node->id)->not->toBeNull();

    $found = $repository->find($node->id);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($node->id)
        ->and($found->treeId)->toBe($this->treeId)
        ->and($found->categoryId)->toBe($this->catAId)
        ->and($found->parentNodeId)->toBeNull()
        ->and($found->position)->toBe(1);
})->group('integration-destructive');

it('finds all nodes belonging to a tree', function (): void {
    /** @var CategoryTreeNodeRepository $repository */
    $repository = $this->nodeRepository;

    $node1 = makeTestNode($this->treeId, $this->catAId);
    $node2 = makeTestNode($this->treeId, $this->catBId);
    $nodeOther = makeTestNode($this->treeId2, $this->catCId);

    $repository->save($node1);
    $repository->save($node2);
    $repository->save($nodeOther);

    $found = $repository->findByTree($this->treeId);

    $ids = array_map(fn (CategoryTreeNode $n) => $n->id, $found);

    expect($found)->toHaveCount(2)
        ->and($ids)->toContain($node1->id)
        ->and($ids)->toContain($node2->id)
        ->and($ids)->not->toContain($nodeOther->id);
})->group('integration-destructive');

it('finds direct children of a parent node sorted by position', function (): void {
    /** @var CategoryTreeNodeRepository $repository */
    $repository = $this->nodeRepository;

    $parent = makeTestNode($this->treeId, $this->catAId, null, 0);
    $repository->save($parent);

    $child2 = makeTestNode($this->treeId, $this->catBId, $parent->id, 2);
    $child1 = makeTestNode($this->treeId, $this->catCId, $parent->id, 1);

    $repository->save($child2);
    $repository->save($child1);

    $children = $repository->findChildren($parent->id, $this->treeId);

    expect($children)->toHaveCount(2)
        ->and($children[0]->id)->toBe($child1->id)
        ->and($children[1]->id)->toBe($child2->id)
        ->and($children[0]->position)->toBe(1)
        ->and($children[1]->position)->toBe(2);
})->group('integration-destructive');

it('finds root nodes of a tree (parent_node_id is null) sorted by position', function (): void {
    /** @var CategoryTreeNodeRepository $repository */
    $repository = $this->nodeRepository;

    $root2 = makeTestNode($this->treeId, $this->catBId, null, 2);
    $root1 = makeTestNode($this->treeId, $this->catAId, null, 1);

    $repository->save($root2);
    $repository->save($root1);

    // Add a child (non-root)
    $child = makeTestNode($this->treeId, $this->catCId, $root1->id, 0);
    $repository->save($child);

    $roots = $repository->findRoots($this->treeId);

    expect($roots)->toHaveCount(2)
        ->and($roots[0]->id)->toBe($root1->id)
        ->and($roots[1]->id)->toBe($root2->id)
        ->and($roots[0]->position)->toBe(1)
        ->and($roots[1]->position)->toBe(2);
})->group('integration-destructive');

it('finds all placements of a category within a tree (multi-placement)', function (): void {
    /** @var CategoryTreeNodeRepository $repository */
    $repository = $this->nodeRepository;

    $root = makeTestNode($this->treeId, $this->catAId, null, 0);
    $repository->save($root);

    // Category B placed twice in the same tree
    $placement1 = makeTestNode($this->treeId, $this->catBId, null, 1);
    $placement2 = makeTestNode($this->treeId, $this->catBId, $root->id, 0);

    // Category B placed in another tree
    $placementOtherTree = makeTestNode($this->treeId2, $this->catBId, null, 0);

    $repository->save($placement1);
    $repository->save($placement2);
    $repository->save($placementOtherTree);

    $found = $repository->findByCategoryInTree($this->catBId, $this->treeId);

    $ids = array_map(fn (CategoryTreeNode $n) => $n->id, $found);

    expect($found)->toHaveCount(2)
        ->and($ids)->toContain($placement1->id)
        ->and($ids)->toContain($placement2->id)
        ->and($ids)->not->toContain($placementOtherTree->id);
})->group('integration-destructive');

it('finds all placements of a category across every tree', function (): void {
    /** @var CategoryTreeNodeRepository $repository */
    $repository = $this->nodeRepository;

    $placement1 = makeTestNode($this->treeId, $this->catAId, null, 0);
    $placement2 = makeTestNode($this->treeId2, $this->catAId, null, 0);
    $other = makeTestNode($this->treeId, $this->catBId, null, 1);

    $repository->save($placement1);
    $repository->save($placement2);
    $repository->save($other);

    $found = $repository->findByCategoryAcrossTrees($this->catAId);

    $ids = array_map(fn (CategoryTreeNode $n) => $n->id, $found);

    expect($found)->toHaveCount(2)
        ->and($ids)->toContain($placement1->id)
        ->and($ids)->toContain($placement2->id)
        ->and($ids)->not->toContain($other->id);
})->group('integration-destructive');

it('deletes a node', function (): void {
    /** @var CategoryTreeNodeRepository $repository */
    $repository = $this->nodeRepository;

    $node = makeTestNode($this->treeId, $this->catAId, null, 0);
    $repository->save($node);

    $id = $node->id;
    expect($id)->not->toBeNull();

    $repository->delete($node);

    $found = $repository->find($id);
    expect($found)->toBeNull();
})->group('integration-destructive');
