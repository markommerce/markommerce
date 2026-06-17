<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeStorefront\LayeredNavigation;

use Marko\Core\Attributes\Preference;
use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Entity\AttributeOption;
use Markommerce\AttributeScope\Entity\AttributeOptionScopedLabels;
use Markommerce\AttributeScope\ScopedOptionLabelResolver;
use Markommerce\Catalog\Exceptions\CategoryNotFoundException;
use Markommerce\Catalog\Filtering\FilterSelection;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\CatalogAttributeIndex\Facet\AttributeFacetQuery;
use Markommerce\CatalogAttributeIndex\Facet\Facet;
use Markommerce\CatalogAttributeIndex\Facet\FacetValue;
use Markommerce\CatalogStorefront\Contracts\LayeredNavigationAssemblerInterface;
use Markommerce\CatalogStorefront\Data\LayeredNavigationData;
use Markommerce\CatalogStorefront\LayeredNavigation\NullLayeredNavigationAssembler;
use Markommerce\Scope\Context\ScopeContext;

#[Preference(replaces: NullLayeredNavigationAssembler::class)]
class LayeredNavigationAssembler implements LayeredNavigationAssemblerInterface
{
    public function __construct(
        private readonly CategoryAssignmentService $categoryAssignmentService,
        private readonly AttributeFacetQuery $attributeFacetQuery,
        private readonly ScopedOptionLabelResolver $scopedOptionLabelResolver,
        private readonly AttributeDefinitionRepositoryInterface $attributeDefinitionRepository,
        private readonly ScopeContext $scopeContext,
    ) {}

    /**
     * @throws CategoryNotFoundException
     */
    public function forCategory(
        int $categoryId,
        ResolvedPaginationOptions $options,
        FilterSelection $selection = new FilterSelection(),
    ): LayeredNavigationData {
        $page = $this->categoryAssignmentService->paginatedProductsInCategory(
            $categoryId,
            $options,
            $selection,
        );

        $rawFacets = $this->attributeFacetQuery->facets($categoryId, $selection);

        $facets        = $this->enrichFacets($rawFacets);
        $activeFilters = $this->buildActiveFilters($selection, $rawFacets);

        return new LayeredNavigationData(
            page: $page,
            facets: $facets,
            activeFilters: $activeFilters,
        );
    }

    /**
     * Enrich facets with scope-resolved display labels for select/multiselect types.
     * Non-option types use the raw value as label.
     *
     * @param list<Facet> $rawFacets
     * @return list<LabeledFacet>
     */
    private function enrichFacets(array $rawFacets): array
    {
        $enriched = [];

        foreach ($rawFacets as $facet) {
            $def = in_array($facet->type, ['select', 'multiselect'], strict: true)
                ? $this->attributeDefinitionRepository->findByCode('product', $facet->code)
                : null;

            $labeledValues = array_map(
                fn (FacetValue $fv): LabeledFacetValue => new LabeledFacetValue(
                    value: $fv->value,
                    label: $this->resolveOptionLabel($def, $fv->value),
                    count: $fv->count,
                    selected: $fv->selected,
                ),
                $facet->values,
            );

            $enriched[] = new LabeledFacet(
                code: $facet->code,
                type: $facet->type,
                values: $labeledValues,
            );
        }

        return $enriched;
    }

    /**
     * Build the list of active filters from the current selection, with resolved display labels.
     *
     * @param list<Facet> $rawFacets
     * @return list<ActiveFilter>
     */
    private function buildActiveFilters(
        FilterSelection $selection,
        array $rawFacets,
    ): array
    {
        if ($selection->isEmpty()) {
            return [];
        }

        $activeFilters = [];

        foreach ($selection->keys() as $code) {
            $values = $selection->forKey($code);

            if ($values === []) {
                continue;
            }

            $type = 'text';

            foreach ($rawFacets as $rawFacet) {
                if ($rawFacet->code === $code) {
                    $type = $rawFacet->type;

                    break;
                }
            }

            $def = in_array($type, ['select', 'multiselect'], strict: true)
                ? $this->attributeDefinitionRepository->findByCode('product', $code)
                : null;

            $labels = array_map(
                fn (string $v): string => $this->resolveOptionLabel($def, $v),
                $values,
            );

            $activeFilters[] = new ActiveFilter(
                code: $code,
                type: $type,
                labels: $labels,
                values: $values,
            );
        }

        return $activeFilters;
    }

    /**
     * Look up the AttributeOption for the given attribute + value and resolve its scoped label.
     * Returns the raw value when the option or its scoped labels companion are not found.
     */
    private function resolveOptionLabel(
        ?AttributeDefinition $def,
        string $value,
    ): string {
        if ($def === null) {
            return $value;
        }

        $options = $this->attributeDefinitionRepository->optionsFor($def);
        $option  = array_find($options, fn (AttributeOption $o): bool => $o->value === $value);

        if ($option === null) {
            return $value;
        }

        $labels = $option->companion(AttributeOptionScopedLabels::class);

        if (!$labels instanceof AttributeOptionScopedLabels) {
            return $option->label !== '' ? $option->label : $value;
        }

        return $this->scopedOptionLabelResolver->resolve($option, $labels, $this->scopeContext);
    }
}
