<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeStorefront\Data;

use Markommerce\CatalogAttributeStorefront\LayeredNavigation\ActiveFilter;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\LabeledFacet;

/**
 * View model for the facet sidebar component.
 *
 * Exposes the labeled facet groups, the active filter chips, and a per-value
 * toggle-URL map (`toggleUrls[attributeCode][value] => url`) consumed by
 * `catalog-attribute-storefront::components/facet-sidebar`.
 */
readonly class FacetSidebarData
{
    /**
     * @param list<LabeledFacet>                      $facets
     * @param list<ActiveFilter>                      $activeFilters
     * @param array<string, array<string, string>>   $toggleUrls
     */
    public function __construct(
        public array $facets = [],
        public array $activeFilters = [],
        public array $toggleUrls = [],
    ) {}
}
