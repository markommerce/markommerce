<?php

declare(strict_types=1);

use Markommerce\CatalogAttributeStorefront\Component\FacetSidebarComponent;
use Markommerce\CatalogStorefront\Context\CategoryToken;
use Markommerce\CatalogStorefront\Controller\CategoryController;
use Markommerce\Layout\LayoutExtension;
use Markommerce\Layout\Operation\MergeProps;
use Markommerce\Layout\Operation\Prepend;
use Markommerce\Layout\Place;
use Markommerce\Layout\Source\Source;

/**
 * Wires layered navigation into the catalog-storefront category page.
 *
 * `catalog-storefront` stays attribute-agnostic; this extension (discovered only
 * when `catalog-attribute-storefront` is installed) does two things:
 *   1. Feeds the bracketed `filter[...]` query array into the product grid so the
 *      listing narrows by the active attribute selection.
 *   2. Renders the facet groups (values + counts + selected state + toggle links) in
 *      the sidebar-left slot.
 *   3. Renders the active-filter chips ("color: red ×" + Clear all) as a bar at the
 *      top of the main content slot, above the product grid (the standard place for
 *      applied filters — next to the results they narrow). Both surfaces are driven by
 *      the same FacetSidebarComponent, rendered through two templates.
 */

// The facet props are shared by both placements (the sidebar groups + the active-filter bar).
$facetProps = [
    'category' => Source::context(CategoryToken::class),
    'page'     => Source::query('page', 1, 'int'),
    'size'     => Source::query('size', 0, 'int'),
    'sort'     => Source::query('sort', '', 'string'),
    'filter'   => Source::query('filter', [], 'array'),
];

return new LayoutExtension(
    handle: [CategoryController::class, 'show'],
    operations: [
        // Feed the filter selection into the grid so the listing narrows.
        new MergeProps(
            name: 'catalog.product_grid',
            props: [
                'filter' => Source::query('filter', [], 'array'),
            ],
        ),
        // Facet groups in the left sidebar.
        new Prepend(
            slotPath: 'sidebar-left',
            placement: new Place(
                component: FacetSidebarComponent::class,
                name: 'catalog.facet_sidebar',
                props: $facetProps,
                slots: [],
                template: 'catalog-attribute-storefront::components/facet-sidebar',
            ),
        ),
        // Active-filter chips bar at the top of the main content column (above the grid).
        // Same component, different template; renders nothing when no filters are active.
        new Prepend(
            slotPath: 'content',
            placement: new Place(
                component: FacetSidebarComponent::class,
                name: 'catalog.active_filters',
                props: $facetProps,
                slots: [],
                template: 'catalog-attribute-storefront::components/active-filters',
            ),
        ),
    ],
    priority: 0,
);
