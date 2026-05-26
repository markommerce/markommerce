<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Exceptions\CategoryTreeNotFoundException;
use Markommerce\Catalog\Exceptions\DefaultTreeMissingException;
use Markommerce\Catalog\Services\CategoryTreeService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeCategoryTreeMarketAssignmentRepository;
use Markommerce\Catalog\Tests\Support\FakeCategoryTreeNodeRepository;
use Markommerce\Catalog\Tests\Support\FakeCategoryTreeRepository;

function makeCategoryTreeServiceForMarket(
    ?FakeCategoryTreeRepository $treeRepo = null,
    ?FakeCategoryTreeMarketAssignmentRepository $assignmentRepo = null,
    ?FakeCategoryTreeNodeRepository $nodeRepo = null,
    ?FakeCategoryRepository $categoryRepo = null,
): CategoryTreeService {
    return new CategoryTreeService(
        categoryTreeRepository: $treeRepo ?? new FakeCategoryTreeRepository(),
        categoryTreeMarketAssignmentRepository: $assignmentRepo ?? new FakeCategoryTreeMarketAssignmentRepository(),
        categoryTreeNodeRepository: $nodeRepo ?? new FakeCategoryTreeNodeRepository(),
        categoryRepository: $categoryRepo ?? new FakeCategoryRepository(),
    );
}

it('assignTreeToMarket stores the assignment for an unknown market', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $assignmentRepo = new FakeCategoryTreeMarketAssignmentRepository();
    $service = makeCategoryTreeServiceForMarket(treeRepo: $treeRepo, assignmentRepo: $assignmentRepo);

    $tree = $service->createTree(code: 'main', name: 'Main', isDefault: true);

    $service->assignTreeToMarket(treeId: $tree->id, market: 'us');

    expect($assignmentRepo->byMarket)->toHaveKey('us');
    expect($assignmentRepo->byMarket['us']->treeId)->toBe($tree->id);
    expect($assignmentRepo->byMarket['us']->market)->toBe('us');
});

it('assignTreeToMarket replaces an existing assignment for the same market', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $assignmentRepo = new FakeCategoryTreeMarketAssignmentRepository();
    $service = makeCategoryTreeServiceForMarket(treeRepo: $treeRepo, assignmentRepo: $assignmentRepo);

    $treeA = $service->createTree(code: 'alpha', name: 'Alpha', isDefault: true);
    $treeB = $service->createTree(code: 'beta', name: 'Beta');

    $service->assignTreeToMarket(treeId: $treeA->id, market: 'us');
    $service->assignTreeToMarket(treeId: $treeB->id, market: 'us');

    expect($assignmentRepo->byMarket)->toHaveCount(1);
    expect($assignmentRepo->byMarket['us']->treeId)->toBe($treeB->id);
});

it('assignTreeToMarket throws CategoryTreeNotFoundException when the tree id is unknown', function (): void {
    $service = makeCategoryTreeServiceForMarket();

    expect(fn () => $service->assignTreeToMarket(treeId: 999, market: 'us'))
        ->toThrow(CategoryTreeNotFoundException::class);
});

it('unassignMarket removes the assignment for the given market', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $assignmentRepo = new FakeCategoryTreeMarketAssignmentRepository();
    $service = makeCategoryTreeServiceForMarket(treeRepo: $treeRepo, assignmentRepo: $assignmentRepo);

    $tree = $service->createTree(code: 'main', name: 'Main', isDefault: true);
    $service->assignTreeToMarket(treeId: $tree->id, market: 'us');

    expect($assignmentRepo->byMarket)->toHaveKey('us');

    $service->unassignMarket(market: 'us');

    expect($assignmentRepo->byMarket)->not->toHaveKey('us');
});

it('unassignMarket is a no-op when no assignment exists for the market', function (): void {
    $assignmentRepo = new FakeCategoryTreeMarketAssignmentRepository();
    $service = makeCategoryTreeServiceForMarket(assignmentRepo: $assignmentRepo);

    // Should not throw
    $service->unassignMarket(market: 'us');

    expect($assignmentRepo->byMarket)->toBeEmpty();
});

it('resolveTreeForMarket returns the tree assigned to the given market', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $assignmentRepo = new FakeCategoryTreeMarketAssignmentRepository();
    $service = makeCategoryTreeServiceForMarket(treeRepo: $treeRepo, assignmentRepo: $assignmentRepo);

    $defaultTree = $service->createTree(code: 'default', name: 'Default', isDefault: true);
    $usTree = $service->createTree(code: 'us', name: 'US Tree');
    $service->assignTreeToMarket(treeId: $usTree->id, market: 'us');

    $resolved = $service->resolveTreeForMarket(market: 'us');

    expect($resolved)->toBeInstanceOf(CategoryTree::class);
    expect($resolved->id)->toBe($usTree->id);
    expect($resolved->code)->toBe('us');
});

it('resolveTreeForMarket returns the default tree when no assignment exists for the market', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $service = makeCategoryTreeServiceForMarket(treeRepo: $treeRepo);

    $defaultTree = $service->createTree(code: 'default', name: 'Default', isDefault: true);

    $resolved = $service->resolveTreeForMarket(market: 'eu');

    expect($resolved)->toBeInstanceOf(CategoryTree::class);
    expect($resolved->id)->toBe($defaultTree->id);
    expect($resolved->code)->toBe('default');
});

it('resolveTreeForMarket throws DefaultTreeMissingException when neither a market assignment nor a default tree exist', function (): void {
    $service = makeCategoryTreeServiceForMarket();

    expect(fn () => $service->resolveTreeForMarket(market: 'us'))
        ->toThrow(DefaultTreeMissingException::class);
});
