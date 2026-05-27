<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Exceptions\CategoryTreeNotFoundException;
use Markommerce\Catalog\Tests\Support\FakeCategoryTreeRepository;
use Markommerce\CatalogMarket\Services\CategoryTreeMarketAssignmentService;
use Markommerce\CatalogMarket\Tests\Support\FakeCategoryTreeMarketAssignmentRepository;

function makeCategoryTreeMarketAssignmentServiceForCatalogMarket(
    ?FakeCategoryTreeRepository $treeRepo = null,
    ?FakeCategoryTreeMarketAssignmentRepository $assignmentRepo = null,
): CategoryTreeMarketAssignmentService {
    return new CategoryTreeMarketAssignmentService(
        categoryTreeMarketAssignmentRepository: $assignmentRepo ?? new FakeCategoryTreeMarketAssignmentRepository(),
        categoryTreeRepository: $treeRepo ?? new FakeCategoryTreeRepository(),
    );
}

it('CategoryTreeMarketAssignmentService::assignTreeToMarket stores the assignment for an unknown market', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $assignmentRepo = new FakeCategoryTreeMarketAssignmentRepository();

    $tree = new CategoryTree();
    $tree->code = 'main';
    $tree->name = 'Main';
    $tree->isDefault = true;
    $treeRepo->save($tree);

    $service = makeCategoryTreeMarketAssignmentServiceForCatalogMarket(treeRepo: $treeRepo, assignmentRepo: $assignmentRepo);

    $service->assignTreeToMarket(treeId: $tree->id, market: 'us');

    expect($assignmentRepo->byMarket)->toHaveKey('us');
    expect($assignmentRepo->byMarket['us']->treeId)->toBe($tree->id);
    expect($assignmentRepo->byMarket['us']->market)->toBe('us');
});

it('CategoryTreeMarketAssignmentService::assignTreeToMarket replaces an existing assignment for the same market', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $assignmentRepo = new FakeCategoryTreeMarketAssignmentRepository();

    $treeA = new CategoryTree();
    $treeA->code = 'alpha';
    $treeA->name = 'Alpha';
    $treeA->isDefault = true;
    $treeRepo->save($treeA);

    $treeB = new CategoryTree();
    $treeB->code = 'beta';
    $treeB->name = 'Beta';
    $treeB->isDefault = false;
    $treeRepo->save($treeB);

    $service = makeCategoryTreeMarketAssignmentServiceForCatalogMarket(treeRepo: $treeRepo, assignmentRepo: $assignmentRepo);

    $service->assignTreeToMarket(treeId: $treeA->id, market: 'us');
    $service->assignTreeToMarket(treeId: $treeB->id, market: 'us');

    expect($assignmentRepo->byMarket)->toHaveCount(1);
    expect($assignmentRepo->byMarket['us']->treeId)->toBe($treeB->id);
});

it('CategoryTreeMarketAssignmentService::assignTreeToMarket throws CategoryTreeNotFoundException when the tree id is unknown', function (): void {
    $service = makeCategoryTreeMarketAssignmentServiceForCatalogMarket();

    expect(fn () => $service->assignTreeToMarket(treeId: 999, market: 'us'))
        ->toThrow(CategoryTreeNotFoundException::class);
});

it('CategoryTreeMarketAssignmentService::unassignMarket removes the assignment for the given market', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $assignmentRepo = new FakeCategoryTreeMarketAssignmentRepository();

    $tree = new CategoryTree();
    $tree->code = 'main';
    $tree->name = 'Main';
    $tree->isDefault = true;
    $treeRepo->save($tree);

    $service = makeCategoryTreeMarketAssignmentServiceForCatalogMarket(treeRepo: $treeRepo, assignmentRepo: $assignmentRepo);

    $service->assignTreeToMarket(treeId: $tree->id, market: 'us');

    expect($assignmentRepo->byMarket)->toHaveKey('us');

    $service->unassignMarket(market: 'us');

    expect($assignmentRepo->byMarket)->not->toHaveKey('us');
});

it('CategoryTreeMarketAssignmentService::unassignMarket is a no-op when no assignment exists for the market', function (): void {
    $assignmentRepo = new FakeCategoryTreeMarketAssignmentRepository();
    $service = makeCategoryTreeMarketAssignmentServiceForCatalogMarket(assignmentRepo: $assignmentRepo);

    // Should not throw
    $service->unassignMarket(market: 'us');

    expect($assignmentRepo->byMarket)->toBeEmpty();
});
