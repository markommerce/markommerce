<?php

declare(strict_types=1);

use Markommerce\CatalogStorefront\Controller\CategoryController;
use Markommerce\CatalogStorefront\Layout\CategoryProductGridLayout;
use Markommerce\Layout\Layout;
use Markommerce\ThemeBlank\Layout\OneColumnLayout;

return new Layout(
    handle: [CategoryController::class, 'show'],
    extends: OneColumnLayout::class,
    context: CategoryProductGridLayout::context(),
    slots: [
        'content' => [
            CategoryProductGridLayout::gridPlacement('catalog-storefront::components/product-grid'),
        ],
    ],
);
