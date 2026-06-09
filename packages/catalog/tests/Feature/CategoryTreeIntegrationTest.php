<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Feature;

use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeServiceInterface;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Exceptions\CategoryHasPlacementsException;
use Markommerce\Catalog\Exceptions\CircularNodeReferenceException;
use Markommerce\Catalog\Services\CategoryService;
use Markommerce\Catalog\Services\CategoryTreeService;
use Markommerce\Catalog\Tests\Support\CategoryFactory;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function categoryTreeVendorDir(): string
{
    return dirname(__DIR__, 4) . '/vendor';
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('materializes the tree with correct nesting and position order against the real database', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::simple(categoryTreeVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeService $treeService */
        $treeService = $store->get(CategoryTreeService::class);

        $categoryFactory = CategoryFactory::new($store);

        $tree = $treeService->createTree('main', 'Main Tree', true);
        $treeId = (int) $tree->id;

        $root       = $categoryFactory->withName('Root')->create();
        $child1     = $categoryFactory->withName('Child 1')->create();
        $child2     = $categoryFactory->withName('Child 2')->create();
        $grandchild = $categoryFactory->withName('Grandchild')->create();

        $rootNode       = $treeService->placeCategory($treeId, (int) $root->id, parentNodeId: null, position: 0);
        $childNode2     = $treeService->placeCategory($treeId, (int) $child2->id, parentNodeId: (int) $rootNode->id, position: 20);
        $childNode1     = $treeService->placeCategory($treeId, (int) $child1->id, parentNodeId: (int) $rootNode->id, position: 10);
        $grandchildNode = $treeService->placeCategory($treeId, (int) $grandchild->id, parentNodeId: (int) $childNode1->id, position: 0);

        $materialized = $treeService->getMaterializedTree($treeId);

        // One root node
        expect($materialized)->toHaveCount(1);

        $rootEntry = $materialized[0];
        expect($rootEntry['category_id'])->toBe((int) $root->id)
            ->and($rootEntry['children'])->toHaveCount(2);

        // Children should be ordered by position (10, 20)
        $firstChild  = $rootEntry['children'][0];
        $secondChild = $rootEntry['children'][1];

        expect($firstChild['category_id'])->toBe((int) $child1->id)
            ->and($secondChild['category_id'])->toBe((int) $child2->id)
            ->and($firstChild['children'])->toHaveCount(1)
            ->and($secondChild['children'])->toHaveCount(0);

        // Grandchild under child1
        $grandchildEntry = $firstChild['children'][0];
        expect($grandchildEntry['category_id'])->toBe((int) $grandchild->id)
            ->and($grandchildEntry['children'])->toHaveCount(0);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('permits the same category to be placed twice within one tree (multi-placement)', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::simple(categoryTreeVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeService $treeService */
        $treeService = $store->get(CategoryTreeService::class);

        $tree   = $treeService->createTree('multi', 'Multi Tree', true);
        $treeId = (int) $tree->id;

        $cat        = CategoryFactory::new($store)->withName('Shared Category')->create();
        $categoryId = (int) $cat->id;

        // Place the same category twice in the same tree
        $node1 = $treeService->placeCategory($treeId, $categoryId, parentNodeId: null, position: 0);
        $node2 = $treeService->placeCategory($treeId, $categoryId, parentNodeId: null, position: 10);

        expect($node1->id)->not->toBeNull()
            ->and($node2->id)->not->toBeNull()
            ->and($node1->id)->not->toBe($node2->id)
            ->and($node1->categoryId)->toBe($categoryId)
            ->and($node2->categoryId)->toBe($categoryId);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('prevents deleting a category that has placements (CategoryHasPlacementsException)', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::simple(categoryTreeVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeService $treeService */
        $treeService = $store->get(CategoryTreeService::class);

        /** @var CategoryService $categoryService */
        $categoryService = $store->get(CategoryService::class);

        $tree   = $treeService->createTree('default', 'Default Tree', true);
        $treeId = (int) $tree->id;

        $cat        = CategoryFactory::new($store)->withName('Placed Category')->create();
        $categoryId = (int) $cat->id;

        $treeService->placeCategory($treeId, $categoryId);

        expect(fn () => $categoryService->delete($categoryId))
            ->toThrow(CategoryHasPlacementsException::class);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('detects a cycle when attempting to move a node under its own descendant', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::simple(categoryTreeVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeService $treeService */
        $treeService = $store->get(CategoryTreeService::class);

        $categoryFactory = CategoryFactory::new($store);

        $tree   = $treeService->createTree('cycle-test', 'Cycle Test Tree', true);
        $treeId = (int) $tree->id;

        $catRoot       = $categoryFactory->withName('Root')->create();
        $catChild      = $categoryFactory->withName('Child')->create();
        $catGrandchild = $categoryFactory->withName('Grandchild')->create();

        $rootNode      = $treeService->placeCategory($treeId, (int) $catRoot->id, parentNodeId: null, position: 0);
        $childNode     = $treeService->placeCategory($treeId, (int) $catChild->id, parentNodeId: (int) $rootNode->id, position: 0);
        $grandchildNode = $treeService->placeCategory($treeId, (int) $catGrandchild->id, parentNodeId: (int) $childNode->id, position: 0);

        // Moving the root node under its own grandchild should throw a cycle exception
        expect(fn () => $treeService->moveNode((int) $rootNode->id, newParentNodeId: (int) $grandchildNode->id, position: 0))
            ->toThrow(CircularNodeReferenceException::class);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
