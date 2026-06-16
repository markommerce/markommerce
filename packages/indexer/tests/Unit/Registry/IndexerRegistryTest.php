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

    $registry->register('product_flat', $indexer);

    expect($registry->get('product_flat'))->toBe($indexer);
    expect($registry->names())->toBe(['product_flat']);
    expect($registry->all())->toBe(['product_flat' => $indexer]);
});

it('throws UnknownIndexException for an unregistered index name', function (): void {
    $registry = new IndexerRegistry();
    $registry->register('product_flat', makeFakeIndexer());

    expect(fn () => $registry->get('nonexistent'))
        ->toThrow(UnknownIndexException::class);
});
