<?php

declare(strict_types=1);

namespace Markommerce\CatalogMarket\Tests\Feature\Repositories;

require_once __DIR__ . '/../Helpers/PostgresTestConnection.php';

use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Repositories\CategoryTreeRepository;
use Markommerce\CatalogMarket\Entity\CategoryTreeMarketAssignment;
use Markommerce\CatalogMarket\Repositories\CategoryTreeMarketAssignmentRepository;
use Markommerce\CatalogMarket\Tests\Feature\Helpers\PostgresTestConnection;

// ─── Helpers ─────────────────────────────────────────────────────────────────

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

// ─── Shared connection & lifecycle ───────────────────────────────────────────

beforeEach(function (): void {
    PostgresTestConnection::skipIfUnavailable();

    $this->conn = new PostgresTestConnection();

    $this->conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_category_trees (
            id         SERIAL PRIMARY KEY,
            code       VARCHAR(64) NOT NULL UNIQUE,
            name       VARCHAR(255) NOT NULL,
            is_default BOOLEAN NOT NULL DEFAULT FALSE
        )',
    );

    $this->conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_category_tree_market_assignments (
            market  VARCHAR(64) PRIMARY KEY,
            tree_id INTEGER REFERENCES catalog_category_trees(id) ON DELETE RESTRICT
        )',
    );

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator($metadataFactory);

    $this->treeRepository = new CategoryTreeRepository($this->conn, $metadataFactory, $hydrator);
    $this->repository = new CategoryTreeMarketAssignmentRepository($this->conn, $metadataFactory, $hydrator);

    // Create a default tree for FK references
    $this->tree = makeCategoryTreeForMarketAssignmentTest('main', 'Main Tree');
    $this->treeRepository->save($this->tree);
});

afterEach(function (): void {
    if (isset($this->conn)) {
        $this->conn->execute('DELETE FROM catalog_category_tree_market_assignments');
        $this->conn->execute('DELETE FROM catalog_category_trees');
    }
});

// ─── Tests ───────────────────────────────────────────────────────────────────

it('persists a new assignment and reads it back by market', function (): void {
    /** @var CategoryTreeMarketAssignmentRepository $repository */
    $repository = $this->repository;

    $assignment = makeMarketAssignment('us', $this->tree->id);
    $repository->save($assignment);

    $found = $repository->findByMarket('us');

    expect($found)->not->toBeNull()
        ->and($found->market)->toBe('us')
        ->and($found->treeId)->toBe($this->tree->id);
})->group('integration-destructive');

it('returns null when finding by a market with no assignment', function (): void {
    /** @var CategoryTreeMarketAssignmentRepository $repository */
    $repository = $this->repository;

    $result = $repository->findByMarket('nonexistent');

    expect($result)->toBeNull();
})->group('integration-destructive');

it('updates an existing assignment when saving for the same market (upsert)', function (): void {
    /** @var CategoryTreeMarketAssignmentRepository $repository */
    $repository = $this->repository;

    $tree2 = makeCategoryTreeForMarketAssignmentTest('secondary', 'Secondary Tree');
    $this->treeRepository->save($tree2);

    $assignment = makeMarketAssignment('de', $this->tree->id);
    $repository->save($assignment);

    $assignment->treeId = $tree2->id;
    $repository->save($assignment);

    $found = $repository->findByMarket('de');

    expect($found)->not->toBeNull()
        ->and($found->market)->toBe('de')
        ->and($found->treeId)->toBe($tree2->id);
})->group('integration-destructive');

it('does not produce a duplicate-key error when saving twice for the same market', function (): void {
    /** @var CategoryTreeMarketAssignmentRepository $repository */
    $repository = $this->repository;

    $tree2 = makeCategoryTreeForMarketAssignmentTest('other', 'Other Tree');
    $this->treeRepository->save($tree2);

    $first = makeMarketAssignment('fr', $this->tree->id);
    $repository->save($first);

    $second = makeMarketAssignment('fr', $tree2->id);
    $repository->save($second);

    $found = $repository->findByMarket('fr');

    expect($found)->not->toBeNull()
        ->and($found->market)->toBe('fr')
        ->and($found->treeId)->toBe($tree2->id);
})->group('integration-destructive');

it('finds all assignments pointing to a given tree', function (): void {
    /** @var CategoryTreeMarketAssignmentRepository $repository */
    $repository = $this->repository;

    $tree2 = makeCategoryTreeForMarketAssignmentTest('alt', 'Alt Tree');
    $this->treeRepository->save($tree2);

    $repository->save(makeMarketAssignment('us', $this->tree->id));
    $repository->save(makeMarketAssignment('de', $this->tree->id));
    $repository->save(makeMarketAssignment('fr', $tree2->id));

    $results = $repository->findByTree($this->tree->id);

    expect($results)->toHaveCount(2)
        ->and(array_column($results, 'market'))->toContain('us')
        ->and(array_column($results, 'market'))->toContain('de');
})->group('integration-destructive');

it('returns the full list of assignments via findAll', function (): void {
    /** @var CategoryTreeMarketAssignmentRepository $repository */
    $repository = $this->repository;

    $repository->save(makeMarketAssignment('us', $this->tree->id));
    $repository->save(makeMarketAssignment('de', $this->tree->id));

    $all = $repository->findAll();

    expect($all->count())->toBe(2);
})->group('integration-destructive');

it('deletes an assignment', function (): void {
    /** @var CategoryTreeMarketAssignmentRepository $repository */
    $repository = $this->repository;

    $assignment = makeMarketAssignment('es', $this->tree->id);
    $repository->save($assignment);

    $repository->delete($assignment);

    $found = $repository->findByMarket('es');
    expect($found)->toBeNull();
})->group('integration-destructive');
