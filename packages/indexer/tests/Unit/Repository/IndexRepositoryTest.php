<?php

declare(strict_types=1);

use Markommerce\Indexer\Repository\IndexRepository;
use Markommerce\Indexer\Tests\Support\FakeConnection;

it('inserts multiple rows in a single statement via the bulk helper', function (): void {
    $connection = new FakeConnection();
    $repo = new IndexRepository($connection);

    $count = $repo->insertRows(
        table: 'product_index',
        columns: ['product_id', 'value'],
        rows: [
            [1, 'foo'],
            [2, 'bar'],
        ],
    );

    expect($count)->toBe(2);
    expect($connection->executed)->toHaveCount(1);

    $sql = $connection->executed[0]['sql'];
    expect($sql)->toContain('INSERT INTO');
    expect($sql)->toContain('product_index');
    expect($sql)->toContain('product_id');
    expect($sql)->toContain('value');

    // Both rows in bindings
    expect($connection->executed[0]['bindings'])->toBe([1, 'foo', 2, 'bar']);
});

it('deletes index rows by entity id', function (): void {
    $connection = new FakeConnection();
    $repo = new IndexRepository($connection);

    $repo->deleteByEntityIds(
        table: 'product_index',
        idColumn: 'product_id',
        ids: [1, 2, 3],
    );

    expect($connection->executed)->toHaveCount(1);

    $sql = $connection->executed[0]['sql'];
    expect($sql)->toContain('DELETE FROM');
    expect($sql)->toContain('product_index');
    expect($sql)->toContain('product_id');
    expect($sql)->toContain('IN');

    expect($connection->executed[0]['bindings'])->toBe([1, 2, 3]);
});

it('truncates the index table', function (): void {
    $connection = new FakeConnection();
    $repo = new IndexRepository($connection);

    $repo->truncate('product_index');

    expect($connection->executed)->toHaveCount(1);

    $sql = $connection->executed[0]['sql'];
    expect($sql)->toContain('TRUNCATE');
    expect($sql)->toContain('product_index');
});

it('rejects an invalid table or column identifier', function (): void {
    $connection = new FakeConnection();
    $repo = new IndexRepository($connection);

    expect(fn () => $repo->insertRows(
        table: 'bad table!',
        columns: ['product_id'],
        rows: [[1]],
    ))->toThrow(InvalidArgumentException::class);

    expect(fn () => $repo->insertRows(
        table: 'product_index',
        columns: ['bad column!'],
        rows: [[1]],
    ))->toThrow(InvalidArgumentException::class);

    expect(fn () => $repo->deleteByEntityIds(
        table: 'bad;table',
        idColumn: 'product_id',
        ids: [1],
    ))->toThrow(InvalidArgumentException::class);

    expect(fn () => $repo->deleteByEntityIds(
        table: 'product_index',
        idColumn: 'bad column',
        ids: [1],
    ))->toThrow(InvalidArgumentException::class);

    expect(fn () => $repo->truncate('bad table!'))->toThrow(InvalidArgumentException::class);
});
