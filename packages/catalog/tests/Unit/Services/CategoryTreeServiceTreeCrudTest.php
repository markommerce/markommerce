<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Exceptions\CannotDeleteDefaultTreeException;
use Markommerce\Catalog\Exceptions\CategoryTreeNotFoundException;
use Markommerce\Catalog\Exceptions\DuplicateDefaultTreeException;
use Markommerce\Catalog\Services\CategoryTreeService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeCategoryTreeNodeRepository;
use Markommerce\Catalog\Tests\Support\FakeCategoryTreeRepository;

function makeCategoryTreeService(
    ?FakeCategoryTreeRepository $treeRepo = null,
    ?FakeCategoryTreeNodeRepository $nodeRepo = null,
    ?FakeCategoryRepository $categoryRepo = null,
): CategoryTreeService {
    return new CategoryTreeService(
        categoryTreeRepository: $treeRepo ?? new FakeCategoryTreeRepository(),
        categoryTreeNodeRepository: $nodeRepo ?? new FakeCategoryTreeNodeRepository(),
        categoryRepository: $categoryRepo ?? new FakeCategoryRepository(),
    );
}

it('createTree persists a new tree with provided code and name', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $service = makeCategoryTreeService(treeRepo: $treeRepo);

    $tree = $service->createTree(code: 'main', name: 'Main Tree');

    expect($tree)->toBeInstanceOf(CategoryTree::class)
        ->and($tree->code)->toBe('main')
        ->and($tree->name)->toBe('Main Tree')
        ->and($tree->isDefault)->toBeFalse()
        ->and($tree->id)->not->toBeNull();

    expect($treeRepo->trees)->toHaveCount(1);
});

it('createTree throws InvalidArgumentException when code is empty or only whitespace', function (): void {
    $service = makeCategoryTreeService();

    expect(fn () => $service->createTree(code: '', name: 'Test'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $service->createTree(code: '   ', name: 'Test'))
        ->toThrow(InvalidArgumentException::class);
});

it('createTree with isDefault=true marks the tree as default', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $service = makeCategoryTreeService(treeRepo: $treeRepo);

    $tree = $service->createTree(code: 'main', name: 'Main Tree', isDefault: true);

    expect($tree->isDefault)->toBeTrue();
    expect($treeRepo->trees)->toHaveCount(1);
    expect(array_values($treeRepo->trees)[0]->isDefault)->toBeTrue();
});

it('createTree with isDefault=true throws DuplicateDefaultTreeException when a default already exists', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $service = makeCategoryTreeService(treeRepo: $treeRepo);

    $service->createTree(code: 'main', name: 'Main Tree', isDefault: true);

    expect(fn () => $service->createTree(code: 'secondary', name: 'Secondary Tree', isDefault: true))
        ->toThrow(DuplicateDefaultTreeException::class);
});

it('setDefaultTree promotes the given tree to default and demotes the previous default in one operation', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $service = makeCategoryTreeService(treeRepo: $treeRepo);

    $oldDefault = $service->createTree(code: 'old', name: 'Old Default', isDefault: true);
    $newTarget = $service->createTree(code: 'new', name: 'New Target');

    $service->setDefaultTree($newTarget->id);

    expect($oldDefault->isDefault)->toBeFalse();
    expect($newTarget->isDefault)->toBeTrue();
});

it('setDefaultTree leaves exactly one default tree after the swap (no overlap, no gap)', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $service = makeCategoryTreeService(treeRepo: $treeRepo);

    $treeA = $service->createTree(code: 'alpha', name: 'Alpha', isDefault: true);
    $treeB = $service->createTree(code: 'beta', name: 'Beta');
    $treeC = $service->createTree(code: 'gamma', name: 'Gamma');

    $service->setDefaultTree($treeB->id);

    $defaults = array_filter(
        array_values($treeRepo->trees),
        fn (CategoryTree $t) => $t->isDefault,
    );

    expect(count($defaults))->toBe(1);
    expect(array_values($defaults)[0]->code)->toBe('beta');
});

it('setDefaultTree throws CategoryTreeNotFoundException when tree id is unknown', function (): void {
    $service = makeCategoryTreeService();

    expect(fn () => $service->setDefaultTree(999))
        ->toThrow(CategoryTreeNotFoundException::class);
});

it('deleteTree removes a non-default tree without market assignments', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $service = makeCategoryTreeService(treeRepo: $treeRepo);

    $defaultTree = $service->createTree(code: 'main', name: 'Main', isDefault: true);
    $otherTree = $service->createTree(code: 'other', name: 'Other');

    expect($treeRepo->trees)->toHaveCount(2);

    $service->deleteTree($otherTree->id);

    expect($treeRepo->trees)->toHaveCount(1);
    expect(array_values($treeRepo->trees)[0]->code)->toBe('main');
});

it('deleteTree throws CategoryTreeNotFoundException when tree id is unknown', function (): void {
    $service = makeCategoryTreeService();

    expect(fn () => $service->deleteTree(999))
        ->toThrow(CategoryTreeNotFoundException::class);
});

it('deleteTree throws CannotDeleteDefaultTreeException when targeting the default tree', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $service = makeCategoryTreeService(treeRepo: $treeRepo);

    $defaultTree = $service->createTree(code: 'main', name: 'Main', isDefault: true);

    expect(fn () => $service->deleteTree($defaultTree->id))
        ->toThrow(CannotDeleteDefaultTreeException::class);
});

it('ensureDefaultTreeExists returns existing default when present', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $service = makeCategoryTreeService(treeRepo: $treeRepo);

    $existing = $service->createTree(code: 'main', name: 'Main', isDefault: true);

    $result = $service->ensureDefaultTreeExists();

    expect($result->id)->toBe($existing->id)
        ->and($result->code)->toBe('main');
    expect($treeRepo->trees)->toHaveCount(1);
});

it('ensureDefaultTreeExists creates a default tree when none exists', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $service = makeCategoryTreeService(treeRepo: $treeRepo);

    $result = $service->ensureDefaultTreeExists();

    expect($result)->toBeInstanceOf(CategoryTree::class)
        ->and($result->code)->toBe('default')
        ->and($result->name)->toBe('Default')
        ->and($result->isDefault)->toBeTrue();
    expect($treeRepo->trees)->toHaveCount(1);
});
