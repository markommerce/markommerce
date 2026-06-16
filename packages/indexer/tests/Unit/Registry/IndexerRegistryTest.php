<?php

declare(strict_types=1);

use Markommerce\Indexer\Contracts\IndexerInterface;
use Markommerce\Indexer\Exceptions\UnknownIndexException;
use Markommerce\Indexer\Registry\IndexerRegistry;

function makeFakeIndexer(): IndexerInterface
{
    return new class () implements IndexerInterface
    {
        public function reindex(array $ids): int
        {
            return count($ids);
        }

        public function reindexOne(int $id): int
        {
            return 1;
        }

        public function rebuildAll(int $chunkSize = 500): int
        {
            return 0;
        }
    };
}

it('registers and retrieves an indexer by name', function (): void {
    $registry = new IndexerRegistry();
    $indexer = makeFakeIndexer();

    $registry->register('product_flat', fn (): IndexerInterface => $indexer);

    expect($registry->get('product_flat'))->toBe($indexer);
    expect($registry->names())->toBe(['product_flat']);
});

it('resolves the indexer lazily only when first retrieved', function (): void {
    $registry = new IndexerRegistry();
    $calls = 0;

    $registry->register('product_flat', function () use (&$calls): IndexerInterface {
        $calls++;

        return makeFakeIndexer();
    });

    expect($calls)->toBe(0);

    $first = $registry->get('product_flat');
    $second = $registry->get('product_flat');

    expect($calls)->toBe(1)
        ->and($second)->toBe($first);
});

it('throws UnknownIndexException for an unregistered index name', function (): void {
    $registry = new IndexerRegistry();
    $registry->register('product_flat', fn (): IndexerInterface => makeFakeIndexer());

    expect(fn () => $registry->get('nonexistent'))
        ->toThrow(UnknownIndexException::class);
});
