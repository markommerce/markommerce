<?php

declare(strict_types=1);

namespace Markommerce\Indexer;

use Markommerce\Indexer\Contracts\IndexerInterface;

abstract class AbstractIndexer implements IndexerInterface
{
    /**
     * Return all entity ids that should be indexed during a full rebuild.
     *
     * @return iterable<int>
     */
    abstract protected function allIds(): iterable;

    /**
     * Produce and persist index rows for the given chunk of ids.
     *
     * @param list<int> $ids
     */
    abstract protected function indexChunk(array $ids): int;

    /**
     * @param list<int> $ids
     */
    public function reindex(array $ids): int
    {
        $total = 0;

        foreach (array_chunk($ids, 500) as $chunk) {
            $total += $this->indexChunk($chunk);
        }

        return $total;
    }

    public function reindexOne(int $id): int
    {
        return $this->reindex([$id]);
    }

    public function rebuildAll(int $chunkSize = 500): int
    {
        $allIds = [];

        foreach ($this->allIds() as $id) {
            $allIds[] = $id;
        }

        $total = 0;

        foreach (array_chunk($allIds, max(1, $chunkSize)) as $chunk) {
            $total += $this->indexChunk($chunk);
        }

        return $total;
    }
}
