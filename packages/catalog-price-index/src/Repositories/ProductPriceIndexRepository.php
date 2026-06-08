<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndex\Repositories;

use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Repository\Repository;
use Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface;
use Markommerce\CatalogPriceIndex\Entity\ProductPriceIndexEntry;

/**
 * @extends Repository<ProductPriceIndexEntry>
 */
class ProductPriceIndexRepository extends Repository implements ProductPriceIndexRepositoryInterface
{
    protected const string ENTITY_CLASS = ProductPriceIndexEntry::class;

    /**
     * Bulk-upsert index entries keyed on product_id in a single SQL statement.
     *
     * Emits exactly one INSERT … ON CONFLICT (product_id) DO UPDATE statement
     * regardless of the number of entries. Empty input is a no-op.
     *
     * @param list<ProductPriceIndexEntry> $entries
     * @throws RepositoryException
     */
    public function upsertMany(array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $valuePlaceholders = [];
        $bindings = [];

        foreach ($entries as $entry) {
            if ($entry->scopes === null) {
                $valuePlaceholders[] = '(?, ?, ?, NULL)';
                $bindings[] = $entry->productId;
                $bindings[] = $entry->amount;
                $bindings[] = $entry->currencyCode;
            } else {
                $valuePlaceholders[] = '(?, ?, ?, ?::jsonb)';
                $bindings[] = $entry->productId;
                $bindings[] = $entry->amount;
                $bindings[] = $entry->currencyCode;
                $bindings[] = json_encode($entry->scopes);
            }
        }

        $sql = sprintf(
            'INSERT INTO "catalog_product_price_index" (product_id, amount, currency_code, scopes) VALUES %s ON CONFLICT (product_id) DO UPDATE SET amount = EXCLUDED.amount, currency_code = EXCLUDED.currency_code, scopes = EXCLUDED.scopes',
            implode(', ', $valuePlaceholders),
        );

        $this->connection->execute($sql, $bindings);
    }

    /**
     * @throws RepositoryException
     */
    public function findByProductId(int $productId): ?ProductPriceIndexEntry
    {
        /** @var ProductPriceIndexEntry|null */
        return $this->findOneBy(['productId' => $productId]);
    }

    /**
     * @throws RepositoryException
     */
    public function truncate(): void
    {
        $this->connection->execute('TRUNCATE TABLE "catalog_product_price_index"');
    }
}
