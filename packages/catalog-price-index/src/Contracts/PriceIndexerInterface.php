<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndex\Contracts;

interface PriceIndexerInterface
{
    /**
     * @param list<int> $ids
     */
    public function reindexProducts(array $ids): int;

    public function reindexProduct(int $id): int;

    /** @param positive-int $chunkSize */
    public function rebuildAll(int $chunkSize = 500): int;
}
