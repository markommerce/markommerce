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
 *   2. Prepends the facet sidebar (values + counts + selected state + toggle links)
 *      to the category content slot.
 */
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
        // Render the facet sidebar ahead of the grid.
        new Prepend(
            slotPath: 'content',
            placement: new Place(
                component: FacetSidebarComponent::class,
                name: 'catalog.facet_sidebar',
                props: [
                    'category' => Source::context(CategoryToken::class),
                    'page'     => Source::query('page', 1, 'int'),
                    'size'     => Source::query('size', 0, 'int'),
                    'sort'     => Source::query('sort', '', 'string'),
                    'filter'   => Source::query('filter', [], 'array'),
                ],
                slots: [],
                template: 'catalog-attribute-storefront::components/facet-sidebar',
            ),
        ),
    ],
    priority: 0,
);
