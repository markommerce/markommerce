<?php

declare(strict_types=1);

namespace Markommerce\CatalogStorefront\LayeredNavigation;

use Markommerce\Catalog\Filtering\FilterSelection;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\CatalogStorefront\Contracts\LayeredNavigationAssemblerInterface;
use Markommerce\CatalogStorefront\Data\LayeredNavigationData;

/**
 * Null-object implementation of `LayeredNavigationAssemblerInterface`.
 *
 * Bound in `catalog-storefront` so the container can always satisfy the
 * `ProductGridComponent` constructor without hard-requiring `catalog-attribute-storefront`.
 * Returns an empty facets/activeFilters list; the product page comes from the
 * `CategoryAssignmentService` unfiltered by attributes.
 *
 * `catalog-attribute-storefront` overrides this binding with the real
 * `LayeredNavigationAssembler` when the package is installed.
 */
class NullLayeredNavigationAssembler implements LayeredNavigationAssemblerInterface
{
    public function __construct(
        private CategoryAssignmentService $categoryAssignmentService,
    ) {}

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

        return new LayeredNavigationData(
            page: $page,
            facets: [],
            activeFilters: [],
        );
    }

    public function selectionFromQuery(array $filter): FilterSelection
    {
        // No attribute knowledge without catalog-attribute-storefront — ignore all filters.
        return new FilterSelection();
    }
}
