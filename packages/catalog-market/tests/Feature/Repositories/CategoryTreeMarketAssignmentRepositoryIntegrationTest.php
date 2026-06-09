<?php

declare(strict_types=1);

namespace Markommerce\CatalogMarket\Tests\Feature\Repositories;

use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Repositories\CategoryTreeRepository;
use Markommerce\CatalogMarket\Entity\CategoryTreeMarketAssignment;
use Markommerce\CatalogMarket\Repositories\CategoryTreeMarketAssignmentRepository;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function catalogMarketAssignRepoVendorDir(): string
{
    return dirname(__DIR__, 5) . '/vendor';
}

function makeCatalogMarketProfile(): StoreProfile
{
    return StoreProfile::of(
        catalogMarketAssignRepoVendorDir(),
        'markommerce/catalog-market',
        'marko/database-pgsql',
    );
}

function makeCategoryTreeForMarketAssignmentTest(string $code, string $name): CategoryTree
{
    $tree = new CategoryTree();
    $tree->code = $code;
    $tree->name = $name;
    $tree->isDefault = false;

    return $tree;
}

function makeMarketAssignment(string $market, ?int $treeId = null): CategoryTreeMarketAssignment
{
    $assignment = new CategoryTreeMarketAssignment();
    $assignment->market = $market;
    $assignment->treeId = $treeId;

    return $assignment;
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('persists a new assignment and reads it back by market', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeCatalogMarketProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeRepository $treeRepository */
        $treeRepository = $store->get(CategoryTreeRepository::class);

        /** @var CategoryTreeMarketAssignmentRepository $repository */
        $repository = $store->get(CategoryTreeMarketAssignmentRepository::class);

        $tree = makeCategoryTreeForMarketAssignmentTest('main', 'Main Tree');
        $treeRepository->save($tree);

        $assignment = makeMarketAssignment('us', (int) $tree->id);
        $repository->save($assignment);

        $found = $repository->findByMarket('us');

        expect($found)->not->toBeNull()
            ->and($found->market)->toBe('us')
            ->and($found->treeId)->toBe($tree->id);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('returns null when finding by a market with no assignment', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeCatalogMarketProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeMarketAssignmentRepository $repository */
        $repository = $store->get(CategoryTreeMarketAssignmentRepository::class);

        $result = $repository->findByMarket('nonexistent');

        expect($result)->toBeNull();
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('updates an existing assignment when saving for the same market (upsert)', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeCatalogMarketProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeRepository $treeRepository */
        $treeRepository = $store->get(CategoryTreeRepository::class);

        /** @var CategoryTreeMarketAssignmentRepository $repository */
        $repository = $store->get(CategoryTreeMarketAssignmentRepository::class);

        $tree = makeCategoryTreeForMarketAssignmentTest('main', 'Main Tree');
        $treeRepository->save($tree);

        $tree2 = makeCategoryTreeForMarketAssignmentTest('secondary', 'Secondary Tree');
        $treeRepository->save($tree2);

        $assignment = makeMarketAssignment('de', (int) $tree->id);
        $repository->save($assignment);

        $assignment->treeId = $tree2->id;
        $repository->save($assignment);

        $found = $repository->findByMarket('de');

        expect($found)->not->toBeNull()
            ->and($found->market)->toBe('de')
            ->and($found->treeId)->toBe($tree2->id);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('does not produce a duplicate-key error when saving twice for the same market', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeCatalogMarketProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeRepository $treeRepository */
        $treeRepository = $store->get(CategoryTreeRepository::class);

        /** @var CategoryTreeMarketAssignmentRepository $repository */
        $repository = $store->get(CategoryTreeMarketAssignmentRepository::class);

        $tree = makeCategoryTreeForMarketAssignmentTest('main', 'Main Tree');
        $treeRepository->save($tree);

        $tree2 = makeCategoryTreeForMarketAssignmentTest('other', 'Other Tree');
        $treeRepository->save($tree2);

        $first = makeMarketAssignment('fr', (int) $tree->id);
        $repository->save($first);

        $second = makeMarketAssignment('fr', (int) $tree2->id);
        $repository->save($second);

        $found = $repository->findByMarket('fr');

        expect($found)->not->toBeNull()
            ->and($found->market)->toBe('fr')
            ->and($found->treeId)->toBe($tree2->id);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('finds all assignments pointing to a given tree', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeCatalogMarketProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeRepository $treeRepository */
        $treeRepository = $store->get(CategoryTreeRepository::class);

        /** @var CategoryTreeMarketAssignmentRepository $repository */
        $repository = $store->get(CategoryTreeMarketAssignmentRepository::class);

        $tree = makeCategoryTreeForMarketAssignmentTest('main', 'Main Tree');
        $treeRepository->save($tree);

        $tree2 = makeCategoryTreeForMarketAssignmentTest('alt', 'Alt Tree');
        $treeRepository->save($tree2);

        $repository->save(makeMarketAssignment('us', (int) $tree->id));
        $repository->save(makeMarketAssignment('de', (int) $tree->id));
        $repository->save(makeMarketAssignment('fr', (int) $tree2->id));

        $results = $repository->findByTree((int) $tree->id);

        expect($results)->toHaveCount(2)
            ->and(array_column($results, 'market'))->toContain('us')
            ->and(array_column($results, 'market'))->toContain('de');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('returns the full list of assignments via findAll', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeCatalogMarketProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeRepository $treeRepository */
        $treeRepository = $store->get(CategoryTreeRepository::class);

        /** @var CategoryTreeMarketAssignmentRepository $repository */
        $repository = $store->get(CategoryTreeMarketAssignmentRepository::class);

        $tree = makeCategoryTreeForMarketAssignmentTest('main', 'Main Tree');
        $treeRepository->save($tree);

        $repository->save(makeMarketAssignment('us', (int) $tree->id));
        $repository->save(makeMarketAssignment('de', (int) $tree->id));

        $all = $repository->findAll();

        expect($all->count())->toBe(2);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('deletes an assignment', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeCatalogMarketProfile());
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeRepository $treeRepository */
        $treeRepository = $store->get(CategoryTreeRepository::class);

        /** @var CategoryTreeMarketAssignmentRepository $repository */
        $repository = $store->get(CategoryTreeMarketAssignmentRepository::class);

        $tree = makeCategoryTreeForMarketAssignmentTest('main', 'Main Tree');
        $treeRepository->save($tree);

        $assignment = makeMarketAssignment('es', (int) $tree->id);
        $repository->save($assignment);

        $repository->delete($assignment);

        $found = $repository->findByMarket('es');
        expect($found)->toBeNull();
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
