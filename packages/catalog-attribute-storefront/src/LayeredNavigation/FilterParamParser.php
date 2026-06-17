<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeStorefront\LayeredNavigation;

use Marko\Routing\Http\Request;
use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Catalog\Filtering\FilterSelection;

/**
 * Parses the `filter[...]` query-param namespace into a FilterSelection.
 *
 * Expected format: `?filter[color][]=red&filter[color][]=blue&filter[size][]=L`
 * PHP natively expands this into: `['color' => ['red', 'blue'], 'size' => ['L']]`.
 *
 * A scalar `filter[color]=red` is normalised to `['red']`.
 * Keys not matching a known facetable attribute code are silently dropped.
 */
class FilterParamParser
{
    public function __construct(
        private readonly AttributeDefinitionRepositoryInterface $attributeDefinitionRepository,
    ) {}

    public function parse(Request $request): FilterSelection
    {
        /** @var mixed $raw */
        $raw = $request->query('filter', []);

        return is_array($raw) ? $this->fromArray($raw) : new FilterSelection();
    }

    /**
     * Build a FilterSelection from an already-extracted bracketed `filter[...]` array,
     * keeping only keys that are known facetable attribute codes.
     *
     * @param array<array-key, mixed> $raw
     */
    public function fromArray(array $raw): FilterSelection
    {
        if ($raw === []) {
            return new FilterSelection();
        }

        $knownCodes = $this->resolveFacetableAttributeCodes();

        /** @var array<string, list<string>> $filters */
        $filters = [];

        foreach ($raw as $key => $value) {
            $code = (string) $key;

            if (!in_array($code, $knownCodes, strict: true)) {
                continue;
            }

            $filters[$code] = $this->normalizeValues($value);
        }

        return new FilterSelection($filters);
    }

    /**
     * @return list<string>
     */
    private function resolveFacetableAttributeCodes(): array
    {
        $collection = $this->attributeDefinitionRepository
            ->query()
            ->where('entity_type', '=', 'product')
            ->where('facetable', '=', true)
            ->getEntities();

        /** @var list<string> $codes */
        $codes = [];

        foreach ($collection as $def) {
            /** @var AttributeDefinition $def */
            $codes[] = $def->code;
        }

        return $codes;
    }

    /**
     * Normalize a raw filter value to a list of strings.
     *
     * An array is returned as-is (with string cast per element).
     * A scalar is wrapped in a single-element list.
     *
     * @param mixed $value
     * @return list<string>
     */
    private function normalizeValues(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map(strval(...), $value));
        }

        return [(string) $value];
    }
}
