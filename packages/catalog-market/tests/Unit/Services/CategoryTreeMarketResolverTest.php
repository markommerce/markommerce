<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Exceptions\CategoryTreeNotFoundException;
use Markommerce\Catalog\Exceptions\DefaultTreeMissingException;
use Markommerce\Catalog\Tests\Support\FakeCategoryTreeRepository;
use Markommerce\CatalogMarket\Entity\CategoryTreeMarketAssignment;
use Markommerce\CatalogMarket\Services\CategoryTreeMarketResolver;
use Markommerce\CatalogMarket\Tests\Support\FakeCategoryTreeMarketAssignmentRepository;

function makeCategoryTreeMarketResolverForCatalogMarket(
    ?FakeCategoryTreeRepository $treeRepo = null,
    ?FakeCategoryTreeMarketAssignmentRepository $assignmentRepo = null,
): CategoryTreeMarketResolver {
    return new CategoryTreeMarketResolver(
        categoryTreeMarketAssignmentRepository: $assignmentRepo ?? new FakeCategoryTreeMarketAssignmentRepository(),
        categoryTreeRepository: $treeRepo ?? new FakeCategoryTreeRepository(),
    );
}

it('CategoryTreeMarketResolver returns the tree assigned to the given market', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $assignmentRepo = new FakeCategoryTreeMarketAssignmentRepository();

    $defaultTree = new CategoryTree();
    $defaultTree->code = 'default';
    $defaultTree->name = 'Default';
    $defaultTree->isDefault = true;
    $treeRepo->save($defaultTree);

    $usTree = new CategoryTree();
    $usTree->code = 'us';
    $usTree->name = 'US Tree';
    $usTree->isDefault = false;
    $treeRepo->save($usTree);

    $assignment = new CategoryTreeMarketAssignment();
    $assignment->market = 'us';
    $assignment->treeId = $usTree->id;
    $assignmentRepo->save($assignment);

    $resolver = makeCategoryTreeMarketResolverForCatalogMarket(treeRepo: $treeRepo, assignmentRepo: $assignmentRepo);

    $resolved = $resolver->resolveTreeForMarket(market: 'us');

    expect($resolved)->toBeInstanceOf(CategoryTree::class);
    expect($resolved->id)->toBe($usTree->id);
    expect($resolved->code)->toBe('us');
});

it('CategoryTreeMarketResolver returns the default tree when no assignment exists for the market', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();

    $defaultTree = new CategoryTree();
    $defaultTree->code = 'default';
    $defaultTree->name = 'Default';
    $defaultTree->isDefault = true;
    $treeRepo->save($defaultTree);

    $resolver = makeCategoryTreeMarketResolverForCatalogMarket(treeRepo: $treeRepo);

    $resolved = $resolver->resolveTreeForMarket(market: 'eu');

    expect($resolved)->toBeInstanceOf(CategoryTree::class);
    expect($resolved->id)->toBe($defaultTree->id);
    expect($resolved->code)->toBe('default');
});

it(
    'CategoryTreeMarketResolver throws CategoryTreeNotFoundException when the assigned tree id no longer exists',
    function (): void {
        $treeRepo = new FakeCategoryTreeRepository();
        $assignmentRepo = new FakeCategoryTreeMarketAssignmentRepository();

        $assignment = new CategoryTreeMarketAssignment();
        $assignment->market = 'us';
        $assignment->treeId = 999;
        $assignmentRepo->save($assignment);

        $resolver = makeCategoryTreeMarketResolverForCatalogMarket(
            treeRepo: $treeRepo,
            assignmentRepo: $assignmentRepo,
        );

        expect(fn () => $resolver->resolveTreeForMarket(market: 'us'))
            ->toThrow(CategoryTreeNotFoundException::class);
    },
);

it(
    'CategoryTreeMarketResolver throws DefaultTreeMissingException when neither a market assignment nor a default tree exist',
    function (): void {
        $resolver = makeCategoryTreeMarketResolverForCatalogMarket();

        expect(fn () => $resolver->resolveTreeForMarket(market: 'us'))
            ->toThrow(DefaultTreeMissingException::class);
    },
);
