<?php

declare(strict_types=1);

use Marko\Database\Repository\RepositoryInterface;
use Markommerce\CatalogMarketCategoryTrees\Contracts\CategoryTreeMarketAssignmentRepositoryInterface;
use Markommerce\CatalogMarketCategoryTrees\Entity\CategoryTreeMarketAssignment;
use Markommerce\CatalogMarketCategoryTrees\Tests\Support\FakeCategoryTreeMarketAssignmentRepository;

it('relocates FakeCategoryTreeMarketAssignmentRepository to the new package\'s tests/Support with the new namespace and still satisfies the relocated interface', function (): void {
    $reflection = new ReflectionClass(FakeCategoryTreeMarketAssignmentRepository::class);

    expect($reflection->getNamespaceName())->toBe('Markommerce\\CatalogMarketCategoryTrees\\Tests\\Support');
    expect($reflection->implementsInterface(CategoryTreeMarketAssignmentRepositoryInterface::class))->toBeTrue();
    expect($reflection->implementsInterface(RepositoryInterface::class))->toBeTrue();
});

it('interface extends Marko\Database\Repository\RepositoryInterface', function (): void {
    $reflection = new ReflectionClass(CategoryTreeMarketAssignmentRepositoryInterface::class);

    expect($reflection->isInterface())->toBeTrue();
    expect($reflection->implementsInterface(RepositoryInterface::class))->toBeTrue();
});

it('interface declares the two custom methods (findByMarket, findByTree)', function (): void {
    $reflection = new ReflectionClass(CategoryTreeMarketAssignmentRepositoryInterface::class);

    $ownMethods = array_map(
        fn (ReflectionMethod $m) => $m->getName(),
        array_filter(
            $reflection->getMethods(),
            fn (ReflectionMethod $m) => $m->getDeclaringClass()->getName() === CategoryTreeMarketAssignmentRepositoryInterface::class,
        ),
    );

    expect($ownMethods)->toContain('findByMarket');
    expect($ownMethods)->toContain('findByTree');
    expect(count($ownMethods))->toBe(2);

    $findByMarket = $reflection->getMethod('findByMarket');
    $marketParams = $findByMarket->getParameters();
    expect($marketParams)->toHaveCount(1);
    expect($marketParams[0]->getName())->toBe('market');
    expect((string) $marketParams[0]->getType())->toBe('string');

    $findByTree = $reflection->getMethod('findByTree');
    $treeParams = $findByTree->getParameters();
    expect($treeParams)->toHaveCount(1);
    expect($treeParams[0]->getName())->toBe('treeId');
    expect((string) $treeParams[0]->getType())->toBe('int');
});

it('fake stores an assignment on save and returns it from findByMarket', function (): void {
    $repository = new FakeCategoryTreeMarketAssignmentRepository();

    $assignment = new CategoryTreeMarketAssignment();
    $assignment->market = 'us';
    $assignment->treeId = 1;

    $repository->save($assignment);

    $found = $repository->findByMarket('us');

    expect($found)->not->toBeNull();
    assert($found !== null);
    expect($found->market)->toBe('us');
    expect($found->treeId)->toBe(1);
});

it('fake save replaces an existing assignment for the same market (upsert)', function (): void {
    $repository = new FakeCategoryTreeMarketAssignmentRepository();

    $assignment = new CategoryTreeMarketAssignment();
    $assignment->market = 'us';
    $assignment->treeId = 1;
    $repository->save($assignment);

    $updated = new CategoryTreeMarketAssignment();
    $updated->market = 'us';
    $updated->treeId = 2;
    $repository->save($updated);

    $found = $repository->findByMarket('us');

    expect($found)->not->toBeNull();
    assert($found !== null);
    expect($found->treeId)->toBe(2);
    expect(count($repository->byMarket))->toBe(1);
});

it('fake findByMarket returns null when no assignment exists for the market', function (): void {
    $repository = new FakeCategoryTreeMarketAssignmentRepository();

    $result = $repository->findByMarket('nonexistent');

    expect($result)->toBeNull();
});

it('fake findByTree returns all markets pointing to the given tree', function (): void {
    $repository = new FakeCategoryTreeMarketAssignmentRepository();

    $us = new CategoryTreeMarketAssignment();
    $us->market = 'us';
    $us->treeId = 1;

    $ca = new CategoryTreeMarketAssignment();
    $ca->market = 'ca';
    $ca->treeId = 1;

    $uk = new CategoryTreeMarketAssignment();
    $uk->market = 'uk';
    $uk->treeId = 2;

    $repository->save($us);
    $repository->save($ca);
    $repository->save($uk);

    $tree1Results = $repository->findByTree(1);

    expect($tree1Results)->toHaveCount(2);
    expect(array_any($tree1Results, fn ($a) => $a->market === 'us'))->toBeTrue();
    expect(array_any($tree1Results, fn ($a) => $a->market === 'ca'))->toBeTrue();

    $tree2Results = $repository->findByTree(2);

    expect($tree2Results)->toHaveCount(1);
    expect($tree2Results[0]->market)->toBe('uk');

    $emptyResults = $repository->findByTree(999);

    expect($emptyResults)->toHaveCount(0);
});

it('fake findAll returns every stored assignment as an EntityCollection', function (): void {
    $repository = new FakeCategoryTreeMarketAssignmentRepository();

    $us = new CategoryTreeMarketAssignment();
    $us->market = 'us';
    $us->treeId = 1;

    $uk = new CategoryTreeMarketAssignment();
    $uk->market = 'uk';
    $uk->treeId = 2;

    $repository->save($us);
    $repository->save($uk);

    $all = $repository->findAll();

    expect($all)->toHaveCount(2);
    expect(array_any($all->toArray(), fn ($a) => $a->market === 'us'))->toBeTrue();
    expect(array_any($all->toArray(), fn ($a) => $a->market === 'uk'))->toBeTrue();
});

it('fake find(string $marketId) finds an assignment by its market PK', function (): void {
    $repository = new FakeCategoryTreeMarketAssignmentRepository();

    $assignment = new CategoryTreeMarketAssignment();
    $assignment->market = 'de';
    $assignment->treeId = 3;

    $repository->save($assignment);

    $found = $repository->find('de');

    expect($found)->not->toBeNull();
    assert($found !== null);
    expect($found->market)->toBe('de');
    expect($found->treeId)->toBe(3);

    $notFound = $repository->find('fr');
    expect($notFound)->toBeNull();
});

it('fake removes an assignment on delete', function (): void {
    $repository = new FakeCategoryTreeMarketAssignmentRepository();

    $assignment = new CategoryTreeMarketAssignment();
    $assignment->market = 'us';
    $assignment->treeId = 1;

    $repository->save($assignment);

    expect($repository->findByMarket('us'))->not->toBeNull();

    $repository->delete($assignment);

    expect($repository->findByMarket('us'))->toBeNull();
    expect(count($repository->byMarket))->toBe(0);
});
