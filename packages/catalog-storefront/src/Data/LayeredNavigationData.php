<?php

declare(strict_types=1);

namespace Markommerce\CatalogStorefront\Data;

use Markommerce\Catalog\Entity\Product;
use Markommerce\Criteria\Page\Page;

/**
 * Transfer object returned by `LayeredNavigationAssemblerInterface::forCategory`.
 *
 * Carries the filtered product page plus the enriched facets and active filters
 * needed to render the layered navigation sidebar.
 *
 * `$facets` holds `LabeledFacet` instances from `catalog-attribute-storefront`.
 * `$activeFilters` holds `ActiveFilter` instances from `catalog-attribute-storefront`.
 * They are typed as `list<object>` here so that `catalog-storefront` does NOT
 * hard-require `catalog-attribute-storefront`.
 */
readonly class LayeredNavigationData
{
    /**
     * @param Page<Product> $page           Filtered product page
     * @param list<object>  $facets         LabeledFacet instances from the assembler
     * @param list<object>  $activeFilters  ActiveFilter instances from the assembler
     */
    public function __construct(
        public Page $page,
        public array $facets,
        public array $activeFilters,
    ) {}
}
