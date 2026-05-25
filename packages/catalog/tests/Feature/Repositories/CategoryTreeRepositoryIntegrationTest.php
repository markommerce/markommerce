<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Feature\Repositories;

require_once __DIR__ . '/../Helpers/PostgresTestConnection.php';

use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Exceptions\DefaultTreeMissingException;
use Markommerce\Catalog\Repositories\CategoryTreeRepository;
use Markommerce\Catalog\Tests\Feature\Helpers\PostgresTestConnection;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeCategoryTree(string $code, string $name, bool $isDefault = false): CategoryTree
{
    $tree = new CategoryTree();
    $tree->code = $code;
    $tree->name = $name;
    $tree->isDefault = $isDefault;

    return $tree;
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

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator($metadataFactory);

    $this->repository = new CategoryTreeRepository($this->conn, $metadataFactory, $hydrator);
});

afterEach(function (): void {
    if (isset($this->conn)) {
        $this->conn->execute('DELETE FROM catalog_category_trees');
    }
});

// ─── Tests ───────────────────────────────────────────────────────────────────

it('persists a tree and reads it back by id', function (): void {
    /** @var CategoryTreeRepository $repository */
    $repository = $this->repository;

    $tree = makeCategoryTree('main', 'Main Tree', true);
    $repository->save($tree);

    expect($tree->id)->not->toBeNull();

    $found = $repository->find($tree->id);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($tree->id)
        ->and($found->code)->toBe('main')
        ->and($found->name)->toBe('Main Tree')
        ->and($found->isDefault)->toBeTrue();
})->group('integration-destructive');

it('finds a tree by its unique code', function (): void {
    /** @var CategoryTreeRepository $repository */
    $repository = $this->repository;

    $tree = makeCategoryTree('sale', 'Sale Tree');
    $repository->save($tree);

    $found = $repository->findByCode('sale');

    expect($found)->not->toBeNull()
        ->and($found->code)->toBe('sale')
        ->and($found->name)->toBe('Sale Tree');
})->group('integration-destructive');

it('returns null when finding by an unknown code', function (): void {
    /** @var CategoryTreeRepository $repository */
    $repository = $this->repository;

    $result = $repository->findByCode('nonexistent');

    expect($result)->toBeNull();
})->group('integration-destructive');

it('finds the default tree when one exists', function (): void {
    /** @var CategoryTreeRepository $repository */
    $repository = $this->repository;

    $tree = makeCategoryTree('default', 'Default Tree', true);
    $repository->save($tree);

    $nonDefault = makeCategoryTree('other', 'Other Tree', false);
    $repository->save($nonDefault);

    $found = $repository->findDefault();

    expect($found)->not->toBeNull()
        ->and($found->code)->toBe('default')
        ->and($found->isDefault)->toBeTrue();
})->group('integration-destructive');

it('throws DefaultTreeMissingException when no default tree exists', function (): void {
    /** @var CategoryTreeRepository $repository */
    $repository = $this->repository;

    $tree = makeCategoryTree('nodefs', 'No Default Tree', false);
    $repository->save($tree);

    expect(fn () => $repository->findDefault())
        ->toThrow(DefaultTreeMissingException::class);
})->group('integration-destructive');

it('deletes a tree', function (): void {
    /** @var CategoryTreeRepository $repository */
    $repository = $this->repository;

    $tree = makeCategoryTree('todelete', 'Tree To Delete');
    $repository->save($tree);

    $id = $tree->id;
    expect($id)->not->toBeNull();

    $repository->delete($tree);

    $found = $repository->find($id);
    expect($found)->toBeNull();
})->group('integration-destructive');
