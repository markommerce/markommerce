<?php

declare(strict_types=1);

use Markommerce\Catalog\Contracts\CategoryTreeRepositoryInterface;
use Markommerce\Catalog\Exceptions\DefaultTreeMissingException;
use Markommerce\Catalog\Services\CategoryTreeService;
use Markommerce\CatalogMarket\Contracts\CategoryTreeMarketAssignmentRepositoryInterface;
use Markommerce\CatalogMarket\Exceptions\TreeHasMarketAssignmentsException;
use Markommerce\CatalogMarket\Services\CategoryTreeMarketAssignmentService;
use Markommerce\CatalogMarket\Services\CategoryTreeMarketResolver;
use Markommerce\Scope\Storage\DefaultScopeGuard;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function tier3VendorDir(): string
{
    return dirname(__DIR__, 4) . '/vendor';
}

/**
 * StoreProfile for the Tier 3 database provisioning.
 */
function tier3StoreProfile(): StoreProfile
{
    return StoreProfile::of(
        tier3VendorDir(),
        'markommerce/catalog-market',
        'marko/database-pgsql',
    );
}

// ─── Tests ────────────────────────────────────────────────────────────────────

beforeEach(function (): void {
    TestConnection::skipIfUnavailable();
    DefaultScopeGuard::reset();

    $testCase = new IntegrationTestCase(tier3StoreProfile());
    $testCase->setUpIntegration();
    $this->testCase = $testCase;

    $store = $testCase->store;

    // CategoryTreeService is resolved through the container so the plugin interceptor fires.
    $this->treeService = $store->get(CategoryTreeService::class);

    $treeRepository       = $store->get(CategoryTreeRepositoryInterface::class);
    $assignmentRepository = $store->get(CategoryTreeMarketAssignmentRepositoryInterface::class);

    $this->treeRepository = $treeRepository;

    $this->assignmentService = new CategoryTreeMarketAssignmentService(
        categoryTreeMarketAssignmentRepository: $assignmentRepository,
        categoryTreeRepository: $treeRepository,
    );

    $this->resolver = new CategoryTreeMarketResolver(
        categoryTreeMarketAssignmentRepository: $assignmentRepository,
        categoryTreeRepository: $treeRepository,
    );
});

afterEach(function (): void {
    DefaultScopeGuard::reset();

    if (isset($this->testCase)) {
        $this->testCase->tearDownIntegration();
        $this->testCase->tearDownClass();
    }
});

it('resolves the assigned non-default tree for each market that has an assignment', function (): void {
    /** @var CategoryTreeService $treeService */
    $treeService = $this->treeService;

    // Create a default tree and two non-default trees
    $defaultTree = $treeService->createTree('default', 'Default Tree', true);
    $usTree = $treeService->createTree('us', 'US Tree');
    $deTree = $treeService->createTree('de', 'DE Tree');

    /** @var CategoryTreeMarketAssignmentService $assignmentService */
    $assignmentService = $this->assignmentService;

    $assignmentService->assignTreeToMarket((int) $usTree->id, 'us');
    $assignmentService->assignTreeToMarket((int) $deTree->id, 'de');

    /** @var CategoryTreeMarketResolver $resolver */
    $resolver = $this->resolver;

    $resolvedUs = $resolver->resolveTreeForMarket('us');
    $resolvedDe = $resolver->resolveTreeForMarket('de');

    expect($resolvedUs->id)->toBe($usTree->id)
        ->and($resolvedUs->code)->toBe('us')
        ->and($resolvedDe->id)->toBe($deTree->id)
        ->and($resolvedDe->code)->toBe('de');
})->group('integration-destructive');

it('falls back to the default tree for a market with no assignment', function (): void {
    /** @var CategoryTreeService $treeService */
    $treeService = $this->treeService;

    $defaultTree = $treeService->createTree('default', 'Default Tree', true);

    /** @var CategoryTreeMarketResolver $resolver */
    $resolver = $this->resolver;

    $resolved = $resolver->resolveTreeForMarket('fr');

    expect($resolved->id)->toBe($defaultTree->id)
        ->and($resolved->isDefault)->toBeTrue();
})->group('integration-destructive');

it('throws DefaultTreeMissingException when neither an assignment nor a default tree exists', function (): void {
    /** @var CategoryTreeService $treeService */
    $treeService = $this->treeService;

    // Create a non-default tree only — no default tree exists
    $treeService->createTree('special', 'Special Tree');

    /** @var CategoryTreeMarketResolver $resolver */
    $resolver = $this->resolver;

    expect(fn () => $resolver->resolveTreeForMarket('unknown'))
        ->toThrow(DefaultTreeMissingException::class);
})->group('integration-destructive');

it(
    'throws TreeHasMarketAssignmentsException via the plugin when deleteTree is called on a tree that still serves a market',
    function (): void {
        /** @var CategoryTreeService $treeService */
        $treeService = $this->treeService;

        $defaultTree = $treeService->createTree('default', 'Default Tree', true);
        $usTree = $treeService->createTree('us', 'US Tree');

        /** @var CategoryTreeMarketAssignmentService $assignmentService */
        $assignmentService = $this->assignmentService;

        $assignmentService->assignTreeToMarket((int) $usTree->id, 'us');

        // deleteTree must be called on the plugin-proxied $treeService — not a raw instance
        expect(fn () => $treeService->deleteTree((int) $usTree->id))
            ->toThrow(TreeHasMarketAssignmentsException::class);
    },
)->group('integration-destructive');

it('allows deleteTree on a non-default tree with no market assignments', function (): void {
    /** @var CategoryTreeService $treeService */
    $treeService = $this->treeService;

    $defaultTree = $treeService->createTree('default', 'Default Tree', true);
    $orphanTree = $treeService->createTree('orphan', 'Orphan Tree');

    // No assignment — deleteTree must succeed without throwing
    $treeService->deleteTree((int) $orphanTree->id);

    $found = $this->treeRepository->find((int) $orphanTree->id);
    expect($found)->toBeNull();
})->group('integration-destructive');

it(
    'replaces an existing assignment when assignTreeToMarket is called twice for the same market with different trees',
    function (): void {
        /** @var CategoryTreeService $treeService */
        $treeService = $this->treeService;

        $defaultTree = $treeService->createTree('default', 'Default Tree', true);
        $firstTree = $treeService->createTree('first', 'First Tree');
        $secondTree = $treeService->createTree('second', 'Second Tree');

        /** @var CategoryTreeMarketAssignmentService $assignmentService */
        $assignmentService = $this->assignmentService;

        $assignmentService->assignTreeToMarket((int) $firstTree->id, 'us');
        $assignmentService->assignTreeToMarket((int) $secondTree->id, 'us');

        /** @var CategoryTreeMarketResolver $resolver */
        $resolver = $this->resolver;

        $resolved = $resolver->resolveTreeForMarket('us');

        expect($resolved->id)->toBe($secondTree->id)
            ->and($resolved->code)->toBe('second');
    },
)->group('integration-destructive');
