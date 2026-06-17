<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeStorefront\Data;

use Markommerce\CatalogAttributeStorefront\LayeredNavigation\ActiveFilter;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\LabeledFacet;

/**
 * View model for the facet sidebar component.
 *
 * Exposes the labeled facet groups, the active filter chips, a per-value
 * toggle-URL map (`toggleUrls[attributeCode][value] => url`), and a
 * clear-all URL consumed by
 * `catalog-attribute-storefront::components/facet-sidebar`.
 */
readonly class FacetSidebarData
{
    /**
     * @param list<LabeledFacet>                      $facets
     * @param list<ActiveFilter>                      $activeFilters
     * @param array<string, array<string, string>>   $toggleUrls
     * @param string|null                             $clearAllUrl  URL that drops all attribute filters (null when no filters active)
     */
    public function __construct(
        public array $facets = [],
        public array $activeFilters = [],
        public array $toggleUrls = [],
        public ?string $clearAllUrl = null,
    ) {}
}
