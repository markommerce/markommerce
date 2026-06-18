<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Feature;

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
        $childNode2     = $treeService->placeCategory(
            $treeId,
            (int) $child2->id,
            parentNodeId: (int) $rootNode->id,
            position: 20,
        );
        $childNode1     = $treeService->placeCategory(
            $treeId,
            (int) $child1->id,
            parentNodeId: (int) $rootNode->id,
            position: 10,
        );
        $grandchildNode = $treeService->placeCategory(
            $treeId,
            (int) $grandchild->id,
            parentNodeId: (int) $childNode1->id,
            position: 0,
        );

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
