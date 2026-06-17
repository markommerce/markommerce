<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeIndex\Repository;

use Marko\Database\Connection\ConnectionInterface;
use Markommerce\CatalogAttributeIndex\Entity\ProductAttributeIndexEntry;
use Markommerce\Indexer\Contracts\IndexRepositoryInterface;

class ProductAttributeIndexRepository
{
    private const string TABLE = 'catalog_product_attribute_index';

    private const string ID_COLUMN = 'product_id';

    /** @var list<string> */
    private const array COLUMNS = [
        'product_id',
        'attribute_code',
        'scope_signature',
        'value_text',
        'value_number',
        'value_bool',
        'value_kind',
    ];

    public function __construct(
        private IndexRepositoryInterface $indexRepository,
        private ConnectionInterface $connection,
    ) {}

    /**
     * Delete all index rows for the given product IDs, then batch-insert the new rows.
     *
     * @param list<int> $productIds
     * @param list<ProductAttributeIndexEntry> $rows
     */
    public function replaceForProducts(
        array $productIds,
        array $rows,
    ): void {
        $this->indexRepository->deleteByEntityIds(self::TABLE, self::ID_COLUMN, $productIds);

        if ($rows === []) {
            return;
        }

        $this->indexRepository->insertRows(
            table: self::TABLE,
            columns: self::COLUMNS,
            rows: array_map(fn (ProductAttributeIndexEntry $entry) => [
                $entry->productId,
                $entry->attributeCode,
                $entry->scopeSignature,
                $entry->valueText,
                $entry->valueNumber,
                $entry->valueBool,
                $entry->valueKind,
            ], $rows),
        );
    }

    public function truncate(): void
    {
        $this->indexRepository->truncate(self::TABLE);
    }

    /**
     * Find all index rows for a (product_id, attribute_code, scope_signature) tuple.
     *
     * Multiselect attributes produce multiple rows; single-valued attributes produce 0 or 1.
     *
     * @return list<ProductAttributeIndexEntry>
     */
    public function findValues(
        int $productId,
        string $code,
        string $signature,
    ): array {
        $sql = sprintf(
            'SELECT "id", "product_id", "attribute_code", "scope_signature", "value_text", "value_number", "value_bool", "value_kind"'
            . ' FROM "%s"'
            . ' WHERE "product_id" = ? AND "attribute_code" = ? AND "scope_signature" = ?',
            self::TABLE,
        );

        $rows = $this->connection->query($sql, [$productId, $code, $signature]);

        return array_values(array_map(function (array $row): ProductAttributeIndexEntry {
            $entry = new ProductAttributeIndexEntry();
            $entry->id = isset($row['id']) ? (int) $row['id'] : null;
            $entry->productId = isset($row['product_id']) ? (int) $row['product_id'] : null;
            $entry->attributeCode = $row['attribute_code'] ?? null;
            $entry->scopeSignature = $row['scope_signature'] ?? null;
            $entry->valueText = $row['value_text'] ?? null;
            $entry->valueNumber = $row['value_number'] ?? null;
            $entry->valueBool = isset($row['value_bool']) ? (bool) $row['value_bool'] : null;
            $entry->valueKind = $row['value_kind'] ?? null;

            return $entry;
        }, $rows));
    }
}
