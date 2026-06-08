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

    /**
     * Find all index entries for the given product IDs, keyed by productId.
     *
     * @param list<int> $productIds
     * @return array<int, ProductPriceIndexEntry> keyed by productId
     */
    public function findByProductIds(array $productIds): array;

    public function truncate(): void;
}
