<?php

declare(strict_types=1);

use Markommerce\Indexer\AbstractIndexer;

// ─── Fakes ────────────────────────────────────────────────────────────────────

/**
 * A concrete subclass that records which chunks were indexed.
 */
class SpyAbstractIndexer extends AbstractIndexer
{
    /** @var list<list<int>> */
    public array $indexedChunks = [];

    /** @param list<int> $allIdsToReturn */
    public function __construct(private array $allIdsToReturn = []) {}

    /** @return iterable<int> */
    protected function allIds(): iterable
    {
        return $this->allIdsToReturn;
    }

    /** @param list<int> $ids */
    protected function indexChunk(array $ids): int
    {
        $this->indexedChunks[] = $ids;

        return count($ids);
    }
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('chunks all ids and indexes each chunk during rebuildAll', function (): void {
    $indexer = new SpyAbstractIndexer([1, 2, 3, 4, 5]);

    $indexer->rebuildAll(chunkSize: 2);

    expect($indexer->indexedChunks)->toHaveCount(3);
    expect($indexer->indexedChunks[0])->toBe([1, 2]);
    expect($indexer->indexedChunks[1])->toBe([3, 4]);
    expect($indexer->indexedChunks[2])->toBe([5]);
});

it('indexes only the given ids during reindex', function (): void {
    $indexer = new SpyAbstractIndexer([1, 2, 3, 4, 5]);

    $indexer->reindex([2, 4]);

    expect($indexer->indexedChunks)->toHaveCount(1);
    expect($indexer->indexedChunks[0])->toBe([2, 4]);
});

it('returns the total count of indexed rows', function (): void {
    // 5 ids chunked by 2 = 3 chunks (2+2+1), indexChunk returns count($ids) each time
    $indexer = new SpyAbstractIndexer([1, 2, 3, 4, 5]);

    $total = $indexer->rebuildAll(chunkSize: 2);

    expect($total)->toBe(5);
});
