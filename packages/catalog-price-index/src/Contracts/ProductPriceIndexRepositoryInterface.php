<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndex\Contracts;

use Markommerce\CatalogPriceIndex\Entity\ProductPriceIndexEntry;

interface ProductPriceIndexRepositoryInterface
{
    /**
     * Bulk-upsert index entries keyed on product_id in a single SQL statement.
     *
     * @param list<ProductPriceIndexEntry> $entries
     */
    public function upsertMany(array $entries): void;

    public function findByProductId(int $productId): ?ProductPriceIndexEntry;

    public function truncate(): void;
}
