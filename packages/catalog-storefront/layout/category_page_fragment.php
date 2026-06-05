<?php

declare(strict_types=1);

use Markommerce\CatalogStorefront\Controller\CategoryController;
use Markommerce\CatalogStorefront\Layout\CategoryProductGridLayout;
use Markommerce\Layout\Layout;

return new Layout(
    handle: [CategoryController::class, 'pageFragment'],
    extends: null,
    context: CategoryProductGridLayout::context(),
    slots: [
        'content' => [
            CategoryProductGridLayout::gridPlacement(
                'catalog-storefront::components/product-grid-fragment',
                '_fragment',
            ),
        ],
    ],
);
