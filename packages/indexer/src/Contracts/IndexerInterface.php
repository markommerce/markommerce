<?php

declare(strict_types=1);

namespace Markommerce\Indexer\Contracts;

interface IndexerInterface
{
    /**
     * @param list<int> $ids
     */
    public function reindex(array $ids): int;

    public function reindexOne(int $id): int;

    public function rebuildAll(int $chunkSize = 500): int;
}
