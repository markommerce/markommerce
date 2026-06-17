<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeStorefront\LayeredNavigation;

use Markommerce\Catalog\Entity\Product;
use Markommerce\Criteria\Page\Page;

readonly class LayeredNavigationResult
{
    /**
     * @param Page<Product>      $page
     * @param list<LabeledFacet> $facets
     * @param list<ActiveFilter> $activeFilters
     */
    public function __construct(
        public Page $page,
        public array $facets,
        public array $activeFilters,
    ) {}
}
