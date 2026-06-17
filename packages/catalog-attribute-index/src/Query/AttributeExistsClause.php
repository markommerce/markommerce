<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeIndex\Query;

use InvalidArgumentException;

/**
 * Builds a correlated EXISTS sub-clause for the catalog_product_attribute_index table.
 *
 * Used by both the facet query (outer column: e.g. `i.product_id`) and the storefront
 * listing filter (outer column: e.g. `catalog_products.id`) to apply attribute value
 * constraints without duplicating SQL logic.
 */
class AttributeExistsClause
{
    private const string QUALIFIED_IDENTIFIER_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*$/';

    /**
     * Build an EXISTS sub-clause correlated to the given outer product column.
     *
     * @param list<string> $values
     * @return array{sql: string, bindings: list<mixed>}
     *
     * @throws InvalidArgumentException
     */
    public function build(
        string $outerColumn,
        string $attributeCode,
        array $values,
        string $signature,
        string $existsAlias = 'aei',
    ): array {
        if (!preg_match(self::QUALIFIED_IDENTIFIER_PATTERN, $outerColumn)) {
            throw new InvalidArgumentException(
                "Invalid outer column identifier: '$outerColumn'. Must be a qualified identifier like 'table.column'.",
            );
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $sql = "EXISTS (SELECT 1 FROM catalog_product_attribute_index $existsAlias WHERE"
            . " $existsAlias.product_id = $outerColumn"
            . " AND $existsAlias.attribute_code = ?"
            . " AND $existsAlias.scope_signature = ?"
            . " AND $existsAlias.value_text IN ($placeholders))";

        $bindings = [$attributeCode, $signature, ...$values];

        return ['sql' => $sql, 'bindings' => $bindings];
    }
}
