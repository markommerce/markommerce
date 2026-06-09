<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Feature\Repositories;

use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Entity\CategoryTreeNode;
use Markommerce\Catalog\Repositories\CategoryRepository;
use Markommerce\Catalog\Repositories\CategoryTreeNodeRepository;
use Markommerce\Catalog\Repositories\CategoryTreeRepository;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function catNodeRepoVendorDir(): string
{
    return dirname(__DIR__, 5) . '/vendor';
}

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

// ─── Tests ───────────────────────────────────────────────────────────────────

it('persists a node and reads it back by id', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::simple(catNodeRepoVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeRepository $treeRepository */
        $treeRepository = $store->get(CategoryTreeRepository::class);

        /** @var CategoryRepository $categoryRepository */
        $categoryRepository = $store->get(CategoryRepository::class);

        /** @var CategoryTreeNodeRepository $nodeRepository */
        $nodeRepository = $store->get(CategoryTreeNodeRepository::class);

        $tree = new CategoryTree();
        $tree->code = 'test-tree';
        $tree->name = 'Test Tree';
        $treeRepository->save($tree);

        $cat = makeCategoryTreeNodeTestCategory('Cat A');
        $categoryRepository->save($cat);

        $node = makeTestNode((int) $tree->id, (int) $cat->id, null, 1);
        $nodeRepository->save($node);

        expect($node->id)->not->toBeNull();

        $found = $nodeRepository->find($node->id);

        expect($found)->not->toBeNull()
            ->and($found->id)->toBe($node->id)
            ->and($found->treeId)->toBe($tree->id)
            ->and($found->categoryId)->toBe($cat->id)
            ->and($found->parentNodeId)->toBeNull()
            ->and($found->position)->toBe(1);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('finds all nodes belonging to a tree', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::simple(catNodeRepoVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeRepository $treeRepository */
        $treeRepository = $store->get(CategoryTreeRepository::class);

        /** @var CategoryRepository $categoryRepository */
        $categoryRepository = $store->get(CategoryRepository::class);

        /** @var CategoryTreeNodeRepository $nodeRepository */
        $nodeRepository = $store->get(CategoryTreeNodeRepository::class);

        $tree = new CategoryTree();
        $tree->code = 'test-tree';
        $tree->name = 'Test Tree';
        $treeRepository->save($tree);

        $tree2 = new CategoryTree();
        $tree2->code = 'other-tree';
        $tree2->name = 'Other Tree';
        $treeRepository->save($tree2);

        $catA = makeCategoryTreeNodeTestCategory('Cat A');
        $categoryRepository->save($catA);
        $catB = makeCategoryTreeNodeTestCategory('Cat B');
        $categoryRepository->save($catB);
        $catC = makeCategoryTreeNodeTestCategory('Cat C');
        $categoryRepository->save($catC);

        $node1 = makeTestNode((int) $tree->id, (int) $catA->id);
        $node2 = makeTestNode((int) $tree->id, (int) $catB->id);
        $nodeOther = makeTestNode((int) $tree2->id, (int) $catC->id);

        $nodeRepository->save($node1);
        $nodeRepository->save($node2);
        $nodeRepository->save($nodeOther);

        $found = $nodeRepository->findByTree((int) $tree->id);

        $ids = array_map(fn (CategoryTreeNode $n) => $n->id, $found);

        expect($found)->toHaveCount(2)
            ->and($ids)->toContain($node1->id)
            ->and($ids)->toContain($node2->id)
            ->and($ids)->not->toContain($nodeOther->id);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('finds direct children of a parent node sorted by position', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::simple(catNodeRepoVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeRepository $treeRepository */
        $treeRepository = $store->get(CategoryTreeRepository::class);

        /** @var CategoryRepository $categoryRepository */
        $categoryRepository = $store->get(CategoryRepository::class);

        /** @var CategoryTreeNodeRepository $nodeRepository */
        $nodeRepository = $store->get(CategoryTreeNodeRepository::class);

        $tree = new CategoryTree();
        $tree->code = 'test-tree';
        $tree->name = 'Test Tree';
        $treeRepository->save($tree);
        $treeId = (int) $tree->id;

        $catA = makeCategoryTreeNodeTestCategory('Cat A');
        $categoryRepository->save($catA);
        $catB = makeCategoryTreeNodeTestCategory('Cat B');
        $categoryRepository->save($catB);
        $catC = makeCategoryTreeNodeTestCategory('Cat C');
        $categoryRepository->save($catC);

        $parent = makeTestNode($treeId, (int) $catA->id, null, 0);
        $nodeRepository->save($parent);

        $child2 = makeTestNode($treeId, (int) $catB->id, (int) $parent->id, 2);
        $child1 = makeTestNode($treeId, (int) $catC->id, (int) $parent->id, 1);

        $nodeRepository->save($child2);
        $nodeRepository->save($child1);

        $children = $nodeRepository->findChildren((int) $parent->id, $treeId);

        expect($children)->toHaveCount(2)
            ->and($children[0]->id)->toBe($child1->id)
            ->and($children[1]->id)->toBe($child2->id)
            ->and($children[0]->position)->toBe(1)
            ->and($children[1]->position)->toBe(2);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('finds root nodes of a tree (parent_node_id is null) sorted by position', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::simple(catNodeRepoVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeRepository $treeRepository */
        $treeRepository = $store->get(CategoryTreeRepository::class);

        /** @var CategoryRepository $categoryRepository */
        $categoryRepository = $store->get(CategoryRepository::class);

        /** @var CategoryTreeNodeRepository $nodeRepository */
        $nodeRepository = $store->get(CategoryTreeNodeRepository::class);

        $tree = new CategoryTree();
        $tree->code = 'test-tree';
        $tree->name = 'Test Tree';
        $treeRepository->save($tree);
        $treeId = (int) $tree->id;

        $catA = makeCategoryTreeNodeTestCategory('Cat A');
        $categoryRepository->save($catA);
        $catB = makeCategoryTreeNodeTestCategory('Cat B');
        $categoryRepository->save($catB);
        $catC = makeCategoryTreeNodeTestCategory('Cat C');
        $categoryRepository->save($catC);

        $root2 = makeTestNode($treeId, (int) $catB->id, null, 2);
        $root1 = makeTestNode($treeId, (int) $catA->id, null, 1);

        $nodeRepository->save($root2);
        $nodeRepository->save($root1);

        // Add a child (non-root)
        $child = makeTestNode($treeId, (int) $catC->id, (int) $root1->id, 0);
        $nodeRepository->save($child);

        $roots = $nodeRepository->findRoots($treeId);

        expect($roots)->toHaveCount(2)
            ->and($roots[0]->id)->toBe($root1->id)
            ->and($roots[1]->id)->toBe($root2->id)
            ->and($roots[0]->position)->toBe(1)
            ->and($roots[1]->position)->toBe(2);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('finds all placements of a category within a tree (multi-placement)', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::simple(catNodeRepoVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeRepository $treeRepository */
        $treeRepository = $store->get(CategoryTreeRepository::class);

        /** @var CategoryRepository $categoryRepository */
        $categoryRepository = $store->get(CategoryRepository::class);

        /** @var CategoryTreeNodeRepository $nodeRepository */
        $nodeRepository = $store->get(CategoryTreeNodeRepository::class);

        $tree = new CategoryTree();
        $tree->code = 'test-tree';
        $tree->name = 'Test Tree';
        $treeRepository->save($tree);
        $treeId = (int) $tree->id;

        $tree2 = new CategoryTree();
        $tree2->code = 'other-tree';
        $tree2->name = 'Other Tree';
        $treeRepository->save($tree2);
        $treeId2 = (int) $tree2->id;

        $catA = makeCategoryTreeNodeTestCategory('Cat A');
        $categoryRepository->save($catA);
        $catB = makeCategoryTreeNodeTestCategory('Cat B');
        $categoryRepository->save($catB);

        $root = makeTestNode($treeId, (int) $catA->id, null, 0);
        $nodeRepository->save($root);

        // Category B placed twice in the same tree
        $placement1 = makeTestNode($treeId, (int) $catB->id, null, 1);
        $placement2 = makeTestNode($treeId, (int) $catB->id, (int) $root->id, 0);

        // Category B placed in another tree
        $placementOtherTree = makeTestNode($treeId2, (int) $catB->id, null, 0);

        $nodeRepository->save($placement1);
        $nodeRepository->save($placement2);
        $nodeRepository->save($placementOtherTree);

        $found = $nodeRepository->findByCategoryInTree((int) $catB->id, $treeId);

        $ids = array_map(fn (CategoryTreeNode $n) => $n->id, $found);

        expect($found)->toHaveCount(2)
            ->and($ids)->toContain($placement1->id)
            ->and($ids)->toContain($placement2->id)
            ->and($ids)->not->toContain($placementOtherTree->id);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('finds all placements of a category across every tree', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::simple(catNodeRepoVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeRepository $treeRepository */
        $treeRepository = $store->get(CategoryTreeRepository::class);

        /** @var CategoryRepository $categoryRepository */
        $categoryRepository = $store->get(CategoryRepository::class);

        /** @var CategoryTreeNodeRepository $nodeRepository */
        $nodeRepository = $store->get(CategoryTreeNodeRepository::class);

        $tree = new CategoryTree();
        $tree->code = 'test-tree';
        $tree->name = 'Test Tree';
        $treeRepository->save($tree);
        $treeId = (int) $tree->id;

        $tree2 = new CategoryTree();
        $tree2->code = 'other-tree';
        $tree2->name = 'Other Tree';
        $treeRepository->save($tree2);
        $treeId2 = (int) $tree2->id;

        $catA = makeCategoryTreeNodeTestCategory('Cat A');
        $categoryRepository->save($catA);
        $catB = makeCategoryTreeNodeTestCategory('Cat B');
        $categoryRepository->save($catB);

        $placement1 = makeTestNode($treeId, (int) $catA->id, null, 0);
        $placement2 = makeTestNode($treeId2, (int) $catA->id, null, 0);
        $other = makeTestNode($treeId, (int) $catB->id, null, 1);

        $nodeRepository->save($placement1);
        $nodeRepository->save($placement2);
        $nodeRepository->save($other);

        $found = $nodeRepository->findByCategoryAcrossTrees((int) $catA->id);

        $ids = array_map(fn (CategoryTreeNode $n) => $n->id, $found);

        expect($found)->toHaveCount(2)
            ->and($ids)->toContain($placement1->id)
            ->and($ids)->toContain($placement2->id)
            ->and($ids)->not->toContain($other->id);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('deletes a node', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::simple(catNodeRepoVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeRepository $treeRepository */
        $treeRepository = $store->get(CategoryTreeRepository::class);

        /** @var CategoryRepository $categoryRepository */
        $categoryRepository = $store->get(CategoryRepository::class);

        /** @var CategoryTreeNodeRepository $nodeRepository */
        $nodeRepository = $store->get(CategoryTreeNodeRepository::class);

        $tree = new CategoryTree();
        $tree->code = 'test-tree';
        $tree->name = 'Test Tree';
        $treeRepository->save($tree);

        $cat = makeCategoryTreeNodeTestCategory('Cat A');
        $categoryRepository->save($cat);

        $node = makeTestNode((int) $tree->id, (int) $cat->id, null, 0);
        $nodeRepository->save($node);

        $id = $node->id;
        expect($id)->not->toBeNull();

        $nodeRepository->delete($node);

        $found = $nodeRepository->find($id);
        expect($found)->toBeNull();
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
