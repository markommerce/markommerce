<?php

declare(strict_types=1);

namespace Markommerce\CatalogStorefront\Layout;

use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogStorefront\Component\ProductCard;
use Markommerce\CatalogStorefront\Component\ProductGridComponent;
use Markommerce\CatalogStorefront\Component\StockBadge;
use Markommerce\CatalogStorefront\Context\CategoryDataProvider;
use Markommerce\CatalogStorefront\Context\CategoryToken;
use Markommerce\CatalogStorefront\Iteration\ProductIteration;
use Markommerce\Layout\Place;
use Markommerce\Layout\Provide;
use Markommerce\Layout\Slot;
use Markommerce\Layout\Source\Source;

/**
 * Shared building blocks for the category product-grid layouts.
 *
 * The full-page layout (`category_show`) and the chrome-less fragment layout
 * (`category_page_fragment`) render the same grid; they differ only in their
 * root grid template and a placement-name suffix (placement names must stay
 * unique across the compiled layout artifact).
 */
class CategoryProductGridLayout
{
    /**
     * The category context provider — resolves the category from the `{id}`
     * route param and exposes it via `CategoryToken`.
     *
     * @return list<Provide>
     */
    public static function context(): array
    {
        return [
            new Provide(
                token: CategoryToken::class,
                provider: CategoryDataProvider::class,
                props: ['id' => Source::route('id', 'int')],
            ),
        ];
    }

    /**
     * The product-grid placement: the grid component, its page/size/sort query
     * props, and the repeated product cards.
     *
     * @param string $template   the root grid template (full page vs fragment)
     * @param string $nameSuffix appended to placement names to keep them unique
     */
    public static function gridPlacement(
        string $template,
        string $nameSuffix = '',
    ): Place {
        return new Place(
            component: ProductGridComponent::class,
            name: 'catalog.product_grid' . $nameSuffix,
            props: [
                'category' => Source::context(CategoryToken::class),
                'page' => Source::query('page', 1, 'int'),
                'size' => Source::query('size', 0, 'int'),
                'sort' => Source::query('sort', '', 'string'),
            ],
            slots: [
                'products' => Slot::repeat(
                    dataKey: 'products',
                    yields: Product::class,
                    as: ProductIteration::class,
                    children: [self::productCard($nameSuffix)],
                ),
            ],
            template: $template,
        );
    }

    private static function productCard(string $nameSuffix): Place
    {
        return new Place(
            component: ProductCard::class,
            name: 'catalog.product_card' . $nameSuffix,
            props: [
                'product' => Source::iterated(ProductIteration::class),
                'formattedPrices' => Source::parentData('formattedPrices', 'array'),
            ],
            slots: [
                'badges' => [
                    new Place(
                        component: StockBadge::class,
                        name: 'catalog.product_card' . $nameSuffix . '.stock_badge',
                        props: ['inStock' => Source::parentData('inStock', 'bool')],
                        slots: [],
                        template: 'catalog-storefront::components/stock-badge',
                    ),
                ],
            ],
            template: 'catalog-storefront::components/product-card',
        );
    }
}
