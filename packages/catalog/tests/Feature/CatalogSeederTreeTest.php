<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Feature;

use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeNodeRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeRepositoryInterface;
use Markommerce\Catalog\Seed\CatalogSeeder;
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
