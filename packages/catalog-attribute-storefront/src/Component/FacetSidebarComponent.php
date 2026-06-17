<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeStorefront\Component;

use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Exceptions\CategoryNotFoundException;
use Markommerce\Catalog\Exceptions\InvalidPaginationConfigException;
use Markommerce\Catalog\Exceptions\PageDepthExceededException;
use Markommerce\Catalog\Pagination\PaginationOptionsResolver;
use Markommerce\CatalogAttributeStorefront\Data\FacetSidebarData;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\ActiveFilter;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\FacetToggleUrlBuilder;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\LabeledFacet;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\LayeredNavigationAssembler;

/**
 * Renders the layered-navigation facet sidebar for the category page.
 *
 * Contributed into the category layout via `layout/extensions/category_facets.php`.
 * Computes facet groups + active filters (through the layered-navigation assembler)
 * and a per-value toggle-URL map (preserving sort/size, resetting the page) so the
 * sidebar links round-trip with the bracketed `filter[...]` query convention.
 */
class FacetSidebarComponent
{
    public function __construct(
        private readonly LayeredNavigationAssembler $layeredNavigationAssembler,
        private readonly PaginationOptionsResolver $paginationOptionsResolver,
        private readonly FacetToggleUrlBuilder $facetToggleUrlBuilder,
    ) {}

    /**
     * @param array<string, mixed> $filter Raw bracketed `filter[...]` query array.
     *
     * @throws CategoryNotFoundException|InvalidPaginationConfigException|PageDepthExceededException
     */
    public function data(
        Category $category,
        int $page,
        int $size,
        string $sort,
        array $filter = [],
    ): FacetSidebarData {
        $id = $category->id;

        if ($id === null) {
            return new FacetSidebarData();
        }

        $options = $this->paginationOptionsResolver->resolve(
            $page,
            $size > 0 ? $size : null,
            $sort !== '' ? $sort : null,
        );

        $selection = $this->layeredNavigationAssembler->selectionFromQuery($filter);
        $navData   = $this->layeredNavigationAssembler->forCategory($id, $options, $selection);

        // The assembler (same package) always emits LabeledFacet / ActiveFilter instances;
        // LayeredNavigationData types them as list<object> only so catalog-storefront stays
        // attribute-agnostic. Narrow them back to the concrete view-model types here.
        /** @var list<LabeledFacet> $facets */
        $facets = $navData->facets;
        /** @var list<ActiveFilter> $activeFilters */
        $activeFilters = $navData->activeFilters;

        return new FacetSidebarData(
            facets: $facets,
            activeFilters: $activeFilters,
            toggleUrls: $this->buildToggleUrls($id, $page, $size, $sort, $filter, $facets),
            clearAllUrl: $this->buildClearAllUrl($id, $size, $sort, $activeFilters),
        );
    }

    /**
     * Build a URL that clears all attribute filters while preserving sort and size.
     * Returns null when no filters are active.
     *
     * @param list<ActiveFilter> $activeFilters
     */
    private function buildClearAllUrl(
        int $categoryId,
        int $size,
        string $sort,
        array $activeFilters,
    ): ?string {
        if ($activeFilters === []) {
            return null;
        }

        $baseUrl = '/catalog/category/' . $categoryId;

        $params = [];

        if ($sort !== '') {
            $params['sort'] = $sort;
        }

        if ($size > 0) {
            $params['size'] = $size;
        }

        $query = $params !== [] ? '?' . http_build_query($params) : '';

        return $baseUrl . $query;
    }

    /**
     * @param array<string, mixed> $filter
     * @param list<LabeledFacet>   $facets
     *
     * @return array<string, array<string, string>>
     */
    private function buildToggleUrls(
        int $categoryId,
        int $page,
        int $size,
        string $sort,
        array $filter,
        array $facets,
    ): array {
        $baseUrl = '/catalog/category/' . $categoryId;

        $currentParams = ['filter' => $filter];

        if ($sort !== '') {
            $currentParams['sort'] = $sort;
        }

        if ($size > 0) {
            $currentParams['size'] = $size;
        }

        $toggleUrls = [];

        foreach ($facets as $facet) {
            foreach ($facet->values as $facetValue) {
                $toggleUrls[$facet->code][$facetValue->value] = $this->facetToggleUrlBuilder->toggle(
                    $baseUrl,
                    $currentParams,
                    $facet->code,
                    $facetValue->value,
                );
            }
        }

        return $toggleUrls;
    }
}
