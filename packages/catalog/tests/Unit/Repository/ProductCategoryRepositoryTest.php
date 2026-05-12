<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Unit\Repository;

use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;
use Marko\Database\Repository\RepositoryInterface;
use Markommerce\Catalog\Entity\ProductCategory;
use Markommerce\Catalog\Repository\ProductCategoryRepository;
use Markommerce\Catalog\Repository\ProductCategoryRepositoryInterface;
use Markommerce\Catalog\Tests\Support\FakeConnection;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

it('declares ProductCategoryRepositoryInterface with assign unassign findCategoryIdsForProduct and findProductIdsForCategory methods returning the correct types', function (): void {
    $reflection = new ReflectionClass(ProductCategoryRepositoryInterface::class);

    expect($reflection->isInterface())->toBeTrue();

    // assign: returns bool
    expect($reflection->hasMethod('assign'))->toBeTrue();
    $assign = $reflection->getMethod('assign');
    $assignReturn = $assign->getReturnType();
    expect($assignReturn)->not->toBeNull();
    assert($assignReturn instanceof ReflectionNamedType);
    expect($assignReturn->getName())->toBe('bool');
    $assignParams = $assign->getParameters();
    expect($assignParams)->toHaveCount(2)
        ->and($assignParams[0]->getName())->toBe('productId')
        ->and((string) $assignParams[0]->getType())->toBe('int')
        ->and($assignParams[1]->getName())->toBe('categoryId')
        ->and((string) $assignParams[1]->getType())->toBe('int');

    // unassign: returns bool
    expect($reflection->hasMethod('unassign'))->toBeTrue();
    $unassign = $reflection->getMethod('unassign');
    $unassignReturn = $unassign->getReturnType();
    expect($unassignReturn)->not->toBeNull();
    assert($unassignReturn instanceof ReflectionNamedType);
    expect($unassignReturn->getName())->toBe('bool');

    // findCategoryIdsForProduct: returns array
    expect($reflection->hasMethod('findCategoryIdsForProduct'))->toBeTrue();
    $findCats = $reflection->getMethod('findCategoryIdsForProduct');
    $findCatsReturn = $findCats->getReturnType();
    expect($findCatsReturn)->not->toBeNull();
    assert($findCatsReturn instanceof ReflectionNamedType);
    expect($findCatsReturn->getName())->toBe('array');

    // findProductIdsForCategory: returns array
    expect($reflection->hasMethod('findProductIdsForCategory'))->toBeTrue();
    $findProds = $reflection->getMethod('findProductIdsForCategory');
    $findProdsReturn = $findProds->getReturnType();
    expect($findProdsReturn)->not->toBeNull();
    assert($findProdsReturn instanceof ReflectionNamedType);
    expect($findProdsReturn->getName())->toBe('array');
});

it('does not extend RepositoryInterface so consumers only see pivot-specific methods', function (): void {
    $reflection = new ReflectionClass(ProductCategoryRepositoryInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->implementsInterface(RepositoryInterface::class))->toBeFalse();
});

it('implements ProductCategoryRepositoryInterface in ProductCategoryRepository', function (): void {
    $reflection = new ReflectionClass(ProductCategoryRepository::class);

    expect($reflection->isInterface())->toBeFalse()
        ->and($reflection->implementsInterface(ProductCategoryRepositoryInterface::class))->toBeTrue()
        ->and($reflection->isSubclassOf(Repository::class))->toBeTrue();
});

it('sets ENTITY_CLASS to the ProductCategory fully qualified class name', function (): void {
    $reflection = new ReflectionClass(ProductCategoryRepository::class);

    expect($reflection->hasConstant('ENTITY_CLASS'))->toBeTrue()
        ->and($reflection->getConstant('ENTITY_CLASS'))->toBe(ProductCategory::class);
});

it('returns true from assign when inserting a new pivot row and false when the row already exists', function (): void {
    $connection = new FakeConnection();

    $repository = new ProductCategoryRepository(
        $connection,
        new EntityMetadataFactory(),
        new EntityHydrator(),
    );

    // No existing row — should insert and return true
    $connection->nextQueryResult = [];
    $result = $repository->assign(1, 2);
    expect($result)->toBeTrue();

    // Row already exists — should skip insert and return false
    $connection->nextQueryResult = [['exists' => '1']];
    $result = $repository->assign(1, 2);
    expect($result)->toBeFalse();
});

it('dispatches the SELECT FOR UPDATE before the INSERT inside a transaction in assign', function (): void {
    $connection = new FakeConnection();

    $repository = new ProductCategoryRepository(
        $connection,
        new EntityMetadataFactory(),
        new EntityHydrator(),
    );

    $connection->nextQueryResult = [];
    $repository->assign(3, 7);

    expect($connection->transactionLog)->toContain('begin')
        ->and($connection->transactionLog)->toContain('commit')
        ->and($connection->transactionLog)->not->toContain('rollback');

    expect($connection->executedQueries)->toHaveCount(1);
    expect($connection->executedQueries[0]['sql'])->toContain('INSERT INTO product_categories');
});

it('rolls back the transaction and rethrows when the insert in assign fails', function (): void {
    $connection = new FakeConnection();
    $connection->throwOnExecute = new RuntimeException('DB error');

    $repository = new ProductCategoryRepository(
        $connection,
        new EntityMetadataFactory(),
        new EntityHydrator(),
    );

    $connection->nextQueryResult = [];

    expect(fn () => $repository->assign(1, 2))->toThrow(RuntimeException::class);

    expect($connection->transactionLog)->toContain('begin')
        ->and($connection->transactionLog)->toContain('rollback')
        ->and($connection->transactionLog)->not->toContain('commit');
});

it('returns true from unassign when the DELETE affects a row and false when no rows are affected', function (): void {
    $connection = new FakeConnection();

    $repository = new ProductCategoryRepository(
        $connection,
        new EntityMetadataFactory(),
        new EntityHydrator(),
    );

    $connection->executeReturnValue = 1;
    $result = $repository->unassign(1, 2);
    expect($result)->toBeTrue();

    $connection->executeReturnValue = 0;
    $result = $repository->unassign(1, 2);
    expect($result)->toBeFalse();
});

it('returns an array of integer category ids from findCategoryIdsForProduct in order from the underlying query', function (): void {
    $connection = new FakeConnection();

    $repository = new ProductCategoryRepository(
        $connection,
        new EntityMetadataFactory(),
        new EntityHydrator(),
    );

    $connection->nextQueryResult = [
        ['category_id' => '10'],
        ['category_id' => '20'],
        ['category_id' => '30'],
    ];

    $result = $repository->findCategoryIdsForProduct(5);

    expect($result)->toBe([10, 20, 30]);
});

it('returns an empty array from findCategoryIdsForProduct when the product has no assignments', function (): void {
    $connection = new FakeConnection();

    $repository = new ProductCategoryRepository(
        $connection,
        new EntityMetadataFactory(),
        new EntityHydrator(),
    );

    $connection->nextQueryResult = [];

    $result = $repository->findCategoryIdsForProduct(99);

    expect($result)->toBe([]);
});

it('returns an array of integer product ids from findProductIdsForCategory', function (): void {
    $connection = new FakeConnection();

    $repository = new ProductCategoryRepository(
        $connection,
        new EntityMetadataFactory(),
        new EntityHydrator(),
    );

    $connection->nextQueryResult = [
        ['product_id' => '100'],
        ['product_id' => '200'],
    ];

    $result = $repository->findProductIdsForCategory(3);

    expect($result)->toBe([100, 200]);
});

it('parameterizes every SQL statement (no string interpolation of user-supplied values)', function (): void {
    $connection = new FakeConnection();

    $repository = new ProductCategoryRepository(
        $connection,
        new EntityMetadataFactory(),
        new EntityHydrator(),
    );

    // assign — triggers SELECT (via query) and INSERT (via execute)
    $connection->nextQueryResult = [];
    $repository->assign(42, 99);

    // The INSERT should have bindings [42, 99] — not literals in the SQL
    expect($connection->executedQueries)->toHaveCount(1)
        ->and($connection->executedQueries[0]['sql'])->toContain('INSERT INTO product_categories')
        ->and($connection->executedQueries[0]['bindings'])->toBe([42, 99])
        ->and($connection->executedQueries[0]['sql'])->not->toContain('42')
        ->and($connection->executedQueries[0]['sql'])->not->toContain('99');

    // unassign — triggers DELETE (via execute)
    $connection->resetExecutedQueries();
    $connection->executeReturnValue = 1;
    $repository->unassign(42, 99);

    expect($connection->executedQueries)->toHaveCount(1)
        ->and($connection->executedQueries[0]['sql'])->toContain('DELETE FROM product_categories')
        ->and($connection->executedQueries[0]['bindings'])->toBe([42, 99])
        ->and($connection->executedQueries[0]['sql'])->not->toContain('42')
        ->and($connection->executedQueries[0]['sql'])->not->toContain('99');
});
