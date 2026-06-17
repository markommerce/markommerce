<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeStorefront\Filter;

use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Catalog\Filtering\FilterSelection;
use Markommerce\Catalog\Filtering\ProductListFilterInterface;
use Markommerce\CatalogAttributeIndex\Query\AttributeExistsClause;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

/**
 * Adds EXISTS constraints over the catalog_product_attribute_index table for each selected
 * attribute value, narrowing the product listing query.
 *
 * Multiple attributes are AND-ed (separate EXISTS clauses); multiple values for the same
 * attribute are OR-ed via IN.
 */
class AttributeProductListFilter implements ProductListFilterInterface
{
    public function __construct(
        private readonly AttributeDefinitionRepositoryInterface $attributeDefinitionRepository,
        private readonly SignatureCandidateEnumerator $signatureCandidateEnumerator,
        private readonly ScopeContext $scopeContext,
        private readonly AttributeExistsClause $attributeExistsClause,
    ) {}

    public function apply(
        RepositoryQueryBuilder $repositoryQueryBuilder,
        FilterSelection $filterSelection,
    ): void {
        if ($filterSelection->isEmpty()) {
            return;
        }

        $defs = $this->resolveFilterableDefs();

        foreach ($filterSelection->keys() as $key) {
            $def = array_find($defs, fn (AttributeDefinition $d): bool => $d->code === $key);

            if ($def === null) {
                continue;
            }

            $values = $filterSelection->forKey($key);

            if ($values === []) {
                continue;
            }

            $signature = $this->resolveSignature($def);

            $clause = $this->attributeExistsClause->build(
                outerColumn: 'catalog_products.id',
                attributeCode: $def->code,
                values: $values,
                signature: $signature,
            );

            $repositoryQueryBuilder->whereRaw($clause['sql'], $clause['bindings']);
        }
    }

    /**
     * @return list<AttributeDefinition>
     */
    private function resolveFilterableDefs(): array
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
     * Resolve the scope signature for the given attribute definition.
     *
     * Picks the most-specific candidate from the enumerator; falls back to '' (base)
     * when the attribute has no axes or no active context values.
     */
    private function resolveSignature(AttributeDefinition $def): string
    {
        $axes = $def->config()['axes'] ?? [];

        if ($axes === []) {
            return '';
        }

        $candidates = $this->signatureCandidateEnumerator->enumerate($axes, $this->scopeContext);

        if ($candidates === []) {
            return '';
        }

        return $candidates[0]->toString();
    }
}
