<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Pagination;

use Markommerce\Catalog\Entity\Product;
use Markommerce\Criteria\Contracts\CursorValueExtractorInterface;
use Markommerce\Criteria\Sort\Sort;

/**
 * Extracts cursor values from a Product entity for keyset pagination.
 *
 * Maps sort key column names to Product entity properties so the keyset
 * strategy can encode/decode next-page anchors.
 *
 * Supported sort key columns: catalog_products.name, catalog_products.sku,
 * catalog_products.price_amount. The id tie-break is handled by the strategy.
 */
class ProductCursorValueExtractor implements CursorValueExtractorInterface
{
    /**
     * @return array<string, scalar>
     */
    public function extract(
        object $entity,
        Sort $sort,
    ): array
    {
        /** @var Product $entity */
        $values = [];

        foreach ($sort->fields as $field) {
            $values[$field->column] = $this->extractValue($entity, $field->column);
        }

        return $values;
    }

    private function extractValue(
        Product $product,
        string $column,
    ): string|int|float
    {
        return match ($column) {
            'catalog_products.name' => $product->name,
            'catalog_products.sku' => $product->sku,
            'catalog_products.price_amount' => (float) ($product->priceAmount ?? 0),
            default => $product->name,
        };
    }
}
