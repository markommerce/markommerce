<?php

declare(strict_types=1);

namespace Markommerce\CatalogStorefront\Contracts;

use Markommerce\Catalog\Filtering\FilterSelection;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;
use Markommerce\CatalogStorefront\Data\LayeredNavigationData;

/**
 * Contract for assembling layered navigation (facets + active filters) for a category page.
 *
 * Defined in `catalog-storefront` so `ProductGridComponent` can accept an optional
 * assembler dependency without hard-requiring the `catalog-attribute-storefront` package.
 * The concrete implementation (`LayeredNavigationAssembler`) lives in `catalog-attribute-storefront`
 * and is bound via that module's DI configuration.
 */
interface LayeredNavigationAssemblerInterface
{
    /**
     * @throws \Markommerce\Catalog\Exceptions\CategoryNotFoundException
     */
    public function forCategory(
        int $categoryId,
        ResolvedPaginationOptions $options,
        FilterSelection $selection = new FilterSelection(),
    ): LayeredNavigationData;

    /**
     * Build a FilterSelection from the raw bracketed `filter[...]` query array,
     * keeping only keys that are known facetable attribute codes.
     *
     * @param array<string, mixed> $filter
     */
    public function selectionFromQuery(array $filter): FilterSelection;
}
