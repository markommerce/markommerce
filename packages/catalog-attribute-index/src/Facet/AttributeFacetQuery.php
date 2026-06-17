<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeIndex\Facet;

use Marko\Database\Connection\ConnectionInterface;
use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Catalog\Filtering\FilterSelection;
use Markommerce\CatalogAttributeIndex\Query\AttributeExistsClause;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

class AttributeFacetQuery
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly AttributeDefinitionRepositoryInterface $attributeDefinitionRepository,
        private readonly SignatureCandidateEnumerator $signatureCandidateEnumerator,
        private readonly ScopeContext $scopeContext,
        private readonly AttributeExistsClause $attributeExistsClause,
    ) {}

    /**
     * Return facet counts for all facetable attributes in the given category.
     *
     * Counts are computed DISJUNCTIVELY: for each attribute, all OTHER selected filters
     * are applied but the attribute's own filter is ignored, so all its values remain countable.
     *
     * @return list<Facet>
     */
    public function facets(
        int $categoryId,
        FilterSelection $selection,
    ): array {
        $defs = $this->resolveFacetableDefs();

        /** @var list<Facet> $facets */
        $facets = [];

        foreach ($defs as $def) {
            $signature = $this->resolveSignature($def, $categoryId);
            $otherSelection = $selection->without($def->code);

            $facetValues = $this->queryFacetValues(
                $def,
                $categoryId,
                $signature,
                $otherSelection,
                $selection,
            );

            $facets[] = new Facet(
                code: $def->code,
                type: $def->type,
                values: $facetValues,
            );
        }

        return $facets;
    }

    /**
     * @return list<AttributeDefinition>
     */
    private function resolveFacetableDefs(): array
    {
        $collection = $this->attributeDefinitionRepository
            ->query()
            ->where('entity_type', '=', 'product')
            ->where('facetable', '=', true)
            ->getEntities();

        /** @var list<AttributeDefinition> $defs */
        $defs = [];

        foreach ($collection as $def) {
            /** @var AttributeDefinition $def */
            $defs[] = $def;
        }

        return $defs;
    }

    /**
     * Resolve the scope signature for a given attribute definition.
     *
     * Walks the candidate list (most-specific first) and picks the first candidate
     * for which the materialized index has rows for this category.
     * Falls back to base '' if none found.
     */
    private function resolveSignature(
        AttributeDefinition $def,
        int $categoryId,
    ): string {
        $axes = $def->config()['axes'] ?? [];

        if ($axes === []) {
            return '';
        }

        $candidates = $this->signatureCandidateEnumerator->enumerate($axes, $this->scopeContext);

        if ($candidates === []) {
            return '';
        }

        foreach ($candidates as $candidate) {
            $sig = $candidate->toString();
            $rows = $this->connection->query(
                'SELECT 1 FROM catalog_product_attribute_index i'
                . ' JOIN catalog_product_category cpc ON cpc.product_id = i.product_id AND cpc.category_id = ?'
                . ' WHERE i.attribute_code = ? AND i.scope_signature = ? LIMIT 1',
                [$categoryId, $def->code, $sig],
            );

            if ($rows !== []) {
                return $sig;
            }
        }

        return '';
    }

    /**
     * Query facet value counts for a single attribute.
     *
     * @return list<FacetValue>
     */
    private function queryFacetValues(
        AttributeDefinition $def,
        int $categoryId,
        string $signature,
        FilterSelection $otherSelection,
        FilterSelection $fullSelection,
    ): array {
        $sql = 'SELECT i.value_text, COUNT(DISTINCT i.product_id) AS cnt'
            . ' FROM catalog_product_attribute_index i'
            . ' JOIN catalog_product_category cpc ON cpc.product_id = i.product_id AND cpc.category_id = ?'
            . ' WHERE i.attribute_code = ? AND i.scope_signature = ?';

        $bindings = [$categoryId, $def->code, $signature];

        // Apply other selected filters as EXISTS constraints (disjunctive)
        foreach ($otherSelection->keys() as $otherCode) {
            $otherValues = $otherSelection->forKey($otherCode);
            $otherSignature = $this->resolveSignatureForCode($otherCode, $def, $categoryId);
            $clause = $this->attributeExistsClause->build(
                outerColumn: 'i.product_id',
                attributeCode: $otherCode,
                values: $otherValues,
                signature: $otherSignature,
            );
            $sql .= ' AND ' . $clause['sql'];
            $bindings = array_merge($bindings, $clause['bindings']);
        }

        $sql .= ' GROUP BY i.value_text';

        $rows = $this->connection->query($sql, $bindings);

        $selectedValues = $fullSelection->forKey($def->code);

        /** @var list<FacetValue> $values */
        $values = [];

        foreach ($rows as $row) {
            $value = (string) $row['value_text'];
            $values[] = new FacetValue(
                value: $value,
                count: (int) $row['cnt'],
                selected: in_array($value, $selectedValues, strict: true),
            );
        }

        return $values;
    }

    /**
     * Resolve the signature for another attribute code used in a filter.
     * We need the attribute definition to look up its axes.
     */
    private function resolveSignatureForCode(
        string $code,
        AttributeDefinition $currentDef,
        int $categoryId,
    ): string {
        // Find the definition for the other attribute
        $collection = $this->attributeDefinitionRepository
            ->query()
            ->where('entity_type', '=', 'product')
            ->getEntities();

        $otherDef = null;

        foreach ($collection as $def) {
            /** @var AttributeDefinition $def */
            if ($def->code === $code) {
                $otherDef = $def;
                break;
            }
        }

        if ($otherDef === null) {
            return '';
        }

        return $this->resolveSignature($otherDef, $categoryId);
    }
}
