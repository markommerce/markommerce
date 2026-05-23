<?php

declare(strict_types=1);

use Markommerce\Catalog\Component\ProductCard;
use Markommerce\Catalog\Component\ProductGridComponent;
use Markommerce\Catalog\Component\StockBadge;
use Markommerce\Catalog\Context\CategoryDataProvider;
use Markommerce\Catalog\Context\CategoryToken;
use Markommerce\Catalog\Controller\CategoryController;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Iteration\ProductIteration;
use Markommerce\Layout\Layout;
use Markommerce\Layout\Place;
use Markommerce\Layout\Provide;
use Markommerce\Layout\Slot;
use Markommerce\Layout\Source\Source;
use Markommerce\ThemeBlank\Layout\OneColumnLayout;

return new Layout(
    handle: [CategoryController::class, 'show'],
    extends: OneColumnLayout::class,
    context: [
        new Provide(
            token: CategoryToken::class,
            provider: CategoryDataProvider::class,
            props: ['id' => Source::route('id', 'int')],
        ),
    ],
    slots: [
        'content' => [
            new Place(
                component: ProductGridComponent::class,
                name: 'catalog.product_grid',
                props: ['category' => Source::context(CategoryToken::class)],
                slots: [
                    'products' => Slot::repeat(
                        dataKey: 'products',
                        yields: Product::class,
                        as: ProductIteration::class,
                        children: [
                            new Place(
                                component: ProductCard::class,
                                name: 'catalog.product_card',
                                props: ['product' => Source::iterated(ProductIteration::class)],
                                slots: [
                                    'badges' => [
                                        new Place(
                                            component: StockBadge::class,
                                            name: 'catalog.product_card.stock_badge',
                                            props: ['inStock' => Source::parentData('inStock', 'bool')],
                                            slots: [],
                                            template: 'catalog::components/stock-badge',
                                        ),
                                    ],
                                ],
                                template: 'catalog::components/product-card',
                            ),
                        ],
                    ),
                ],
                template: 'catalog::components/product-grid',
            ),
        ],
    ],
);
