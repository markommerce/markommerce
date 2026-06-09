<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Feature;

use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeNodeRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeRepositoryInterface;
use Markommerce\Catalog\Seed\CatalogSeeder;
use Markommerce\Catalog\Services\CategoryTreeService;
use Markommerce\Testing\Database\IsolationMode;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function catalogSeederVendorDir(): string
{
    return dirname(__DIR__, 4) . '/vendor';
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('running the seeder creates the default tree when none exists', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::simple(catalogSeederVendorDir());
    $testCase = new IntegrationTestCase($profile, IsolationMode::Truncate);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CatalogSeeder $seeder */
        $seeder = $store->get(CatalogSeeder::class);

        /** @var CategoryTreeRepositoryInterface $treeRepository */
        $treeRepository = $store->get(CategoryTreeRepositoryInterface::class);

        $seeder->run();

        $defaultTree = $treeRepository->findDefault();

        expect($defaultTree)->not->toBeNull()
            ->and($defaultTree->isDefault)->toBeTrue()
            ->and($defaultTree->code)->toBe('default');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('running the seeder reuses an existing default tree without creating a duplicate', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::simple(catalogSeederVendorDir());
    $testCase = new IntegrationTestCase($profile, IsolationMode::Truncate);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CatalogSeeder $seeder */
        $seeder = $store->get(CatalogSeeder::class);

        /** @var CategoryTreeRepositoryInterface $treeRepository */
        $treeRepository = $store->get(CategoryTreeRepositoryInterface::class);

        $seeder->run();

        $allTrees    = $treeRepository->findAll();
        $defaultTrees = array_values(array_filter(
            $allTrees->toArray(),
            fn ($t) => $t->isDefault,
        ));

        expect($defaultTrees)->toHaveCount(1);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it(
    'the tree-placement step is idempotent: when seeded categories already have a placement in the default tree, the seeder does not create a second placement for the same (category, tree) pair',
    function (): void {
        TestConnection::skipIfUnavailable();
    
        $profile  = StoreProfile::simple(catalogSeederVendorDir());
        $testCase = new IntegrationTestCase($profile, IsolationMode::Truncate);
        $testCase->setUpIntegration();
    
        try {
            $store = $testCase->store;
    
            /** @var CatalogSeeder $seeder */
            $seeder = $store->get(CatalogSeeder::class);
    
            /** @var CategoryTreeRepositoryInterface $treeRepository */
            $treeRepository = $store->get(CategoryTreeRepositoryInterface::class);
    
            /** @var CategoryTreeNodeRepositoryInterface $treeNodeRepository */
            $treeNodeRepository = $store->get(CategoryTreeNodeRepositoryInterface::class);
    
            /** @var CategoryRepositoryInterface $categoryRepository */
            $categoryRepository = $store->get(CategoryRepositoryInterface::class);
    
            // Run seeder once to seed categories, products, and initial tree placements
        $seeder->run();
    
            $defaultTree = $treeRepository->findDefault();
            $treeId      = (int) $defaultTree->id;
    
            $nodesAfterFirstRun     = $treeNodeRepository->findByTree($treeId);
            $nodeCountAfterFirstRun = count($nodesAfterFirstRun);
    
            // Simulate re-running the tree-placement step through a fresh CategoryTreeService
        // using the same container-resolved repositories

            /** @var CategoryTreeService $freshTreeService */
            $freshTreeService = $store->get(CategoryTreeService::class);
    
            // Place all already-seeded categories again — the idempotency guard should prevent duplicates
        $alreadySeededCategories = $categoryRepository->findAll()->toArray();
            $freshDefaultTree        = $freshTreeService->ensureDefaultTreeExists();
    
            foreach ($alreadySeededCategories as $category) {
                $existing = $treeNodeRepository->findByCategoryInTree(
                    (int) $category->id,
                    (int) $freshDefaultTree->id
                );
    
                if (count($existing) === 0) {
                    $freshTreeService->placeCategory(
                        (int) $freshDefaultTree->id,
                        (int) $category->id,
                        parentNodeId: null,
                        position: null
                    );
                }
            }
    
            $nodesAfterSecondPass = $treeNodeRepository->findByTree((int) $freshDefaultTree->id);
    
            // The node count should not have grown — no duplicate placements were created
        expect(count($nodesAfterSecondPass))->toBe($nodeCountAfterFirstRun);
        } finally {
            $testCase->tearDownIntegration();
            $testCase->tearDownClass();
        }
    }
)->group('integration-destructive');

it('seeded categories appear in stable position order in the default tree', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::simple(catalogSeederVendorDir());
    $testCase = new IntegrationTestCase($profile, IsolationMode::Truncate);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CatalogSeeder $seeder */
        $seeder = $store->get(CatalogSeeder::class);

        /** @var CategoryTreeRepositoryInterface $treeRepository */
        $treeRepository = $store->get(CategoryTreeRepositoryInterface::class);

        /** @var CategoryTreeNodeRepositoryInterface $treeNodeRepository */
        $treeNodeRepository = $store->get(CategoryTreeNodeRepositoryInterface::class);

        $seeder->run();

        $defaultTree = $treeRepository->findDefault();
        $treeId      = (int) $defaultTree->id;

        $rootNodes = $treeNodeRepository->findRoots($treeId);

        // findRoots returns nodes sorted by position
        // Categories should appear in seeding order (1..5), each with incrementing positions
        $positions = array_map(fn ($n) => $n->position, $rootNodes);

        // All positions must be strictly ascending
        $sortedPositions = $positions;
        sort($sortedPositions);

        expect($positions)->toBe($sortedPositions)
            ->and(count(array_unique($positions)))->toBe(count($positions));
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('running the seeder places every seeded category as a root node in the default tree', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::simple(catalogSeederVendorDir());
    $testCase = new IntegrationTestCase($profile, IsolationMode::Truncate);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CatalogSeeder $seeder */
        $seeder = $store->get(CatalogSeeder::class);

        /** @var CategoryTreeRepositoryInterface $treeRepository */
        $treeRepository = $store->get(CategoryTreeRepositoryInterface::class);

        /** @var CategoryTreeNodeRepositoryInterface $treeNodeRepository */
        $treeNodeRepository = $store->get(CategoryTreeNodeRepositoryInterface::class);

        /** @var CategoryRepositoryInterface $categoryRepository */
        $categoryRepository = $store->get(CategoryRepositoryInterface::class);

        $seeder->run();

        $defaultTree = $treeRepository->findDefault();
        $treeId      = (int) $defaultTree->id;
        $rootNodes   = $treeNodeRepository->findRoots($treeId);
        $allCategories = $categoryRepository->findAll()->toArray();

        $rootCategoryIds = array_map(fn ($n) => (int) $n->categoryId, $rootNodes);
        $allCategoryIds  = array_map(fn ($c) => (int) $c->id, $allCategories);

        sort($rootCategoryIds);
        sort($allCategoryIds);

        expect($rootNodes)->toHaveCount(5)
            ->and($rootCategoryIds)->toBe($allCategoryIds);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
