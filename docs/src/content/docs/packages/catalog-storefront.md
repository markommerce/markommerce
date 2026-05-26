---
title: markommerce/catalog-storefront
description: Public storefront for markommerce/catalog --- HTTP controllers, Latte templates, layout glue, and theme integration.
---

Public storefront for `markommerce/catalog`. `markommerce/catalog-storefront` provides the `CategoryController`, layout definition, `ProductGridComponent`, `ProductCard`, `StockBadge`, and Latte templates that turn the catalog domain into a browsable storefront. Installing the package is enough to get a working product grid at `GET /catalog/category/{id}` --- no manual wiring required. To add locale-aware name and description resolution, install [markommerce/catalog-storefront-scope](/docs/packages/catalog-storefront-scope/).

## Installation

```bash
composer require markommerce/catalog-storefront
```

`markommerce/catalog` is declared as a Composer dependency and installed automatically. The package registers its module bindings and layout definition via `module.php`. No manual service binding is required.

## Usage

### Storefront route

The module registers the following route automatically via `CategoryController`:

```
GET /catalog/category/{id}
```

`CategoryController` performs a category lookup by `id` and returns a `404` response when the category does not exist. The page is rendered by `markommerce/layout` --- `CategoryController` carries no `#[Layout]` attribute; placement is declared entirely in the layout definition file.

### Layout definition

The category page layout is declared in `layout/category_show.php`. It extends `OneColumnLayout` from `markommerce/theme-blank`, provides the category via `CategoryDataProvider`, places `ProductGridComponent` in the `content` slot, and uses a `Slot::repeat()` to render a `ProductCard` for each product:

```php title="packages/catalog-storefront/layout/category_show.php"
<?php

declare(strict_types=1);

use Markommerce\CatalogStorefront\Component\ProductCard;
use Markommerce\CatalogStorefront\Component\ProductGridComponent;
use Markommerce\CatalogStorefront\Component\StockBadge;
use Markommerce\CatalogStorefront\Context\CategoryDataProvider;
use Markommerce\CatalogStorefront\Context\CategoryToken;
use Markommerce\CatalogStorefront\Controller\CategoryController;
use Markommerce\CatalogStorefront\Iteration\ProductIteration;
use Markommerce\Catalog\Entity\Product;
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
                                            template: 'catalog-storefront::components/stock-badge',
                                        ),
                                    ],
                                ],
                                template: 'catalog-storefront::components/product-card',
                            ),
                        ],
                    ),
                ],
                template: 'catalog-storefront::components/product-grid',
            ),
        ],
    ],
);
```

### Template overrides

The package ships Latte templates for the category page, product grid, product card, and stock badge. To override a template, place a file at the same relative path inside your application's template directory. Marko resolves templates using the same override chain as module Preferences --- the last registered template wins.

### Swapping the theme

The layout definition extends `OneColumnLayout` from `markommerce/theme-blank`. To swap themes, override the layout definition in your application's `module.php` by binding your own `Layout` instance for the `CategoryController::show` handle. No forking required.

## API Reference

### `CategoryController`

| Method | Route | Description |
|---|---|---|
| `show(int $id)` | `GET /catalog/category/{id}` | Resolve the category by `id` and render the product grid page. Returns `404` when the category does not exist. |

### `ProductGridComponent`

A placement-agnostic component that resolves products for a category and builds `ProductGridData`. Its `data(Category $category)` method loads the products assigned to the category and populates `resolvedNames` and `resolvedDescs` with the raw entity values.

When [markommerce/catalog-storefront-scope](/docs/packages/catalog-storefront-scope/) is installed, its `ScopedProductGridComponent` Preference replaces this component and resolves locale-aware names and descriptions via `ScopeResolver`.

| Method | Return type | Description |
|---|---|---|
| `data(Category $category)` | `ProductGridData` | Load products for the category; return a `ProductGridData` DTO with raw name and description values. |

### `ProductCard`

The per-item component rendered inside the `products` repeat slot. Its `data(Product $product)` method returns a `ProductCardData` DTO.

| Method | Return type | Description |
|---|---|---|
| `data(Product $product)` | `ProductCardData` | Build per-product display data including resolved name, description, and stock status. |

### `StockBadge`

Displays the stock status for a single product. Rendered inside the `badges` slot of `ProductCard`. Receives the `inStock` flag sourced from the parent `ProductCardData` via `Source::parentData()`.

| Method | Return type | Description |
|---|---|---|
| `data(bool $inStock)` | `StockBadgeData` | Return a `StockBadgeData` DTO wrapping the stock status flag. |

### `ProductGridData`

DTO returned by `ProductGridComponent::data()`. Extends `ExtensibleData`.

| Property | Type | Description |
|---|---|---|
| `$category` | `Category` | The resolved category entity |
| `$products` | `list<Product>` | All products assigned to the category |
| `$resolvedNames` | `array<int, string>` | Display name keyed by product ID (raw value; scope-resolved when `catalog-storefront-scope` is installed) |
| `$resolvedDescs` | `array<int, string\|null>` | Display description keyed by product ID (raw value; scope-resolved when `catalog-storefront-scope` is installed) |
| `$extensions` | `ExtensionBag` | Typed extension attributes (third-party use) |

### `ProductCardData`

DTO returned by `ProductCard::data()`. Extends `ExtensibleData`.

| Property | Type | Description |
|---|---|---|
| `$product` | `Product` | The product entity |
| `$resolvedName` | `string` | Display name (raw value; scope-resolved when `catalog-storefront-scope` is installed) |
| `$resolvedDesc` | `string` | Display description, defaults to empty string when the product has no description (raw value; scope-resolved when `catalog-storefront-scope` is installed) |
| `$inStock` | `bool` | Whether the product is currently in stock |
| `$extensions` | `ExtensionBag` | Typed extension attributes (third-party use) |

Both `ProductGridData` and `ProductCardData` extend `ExtensibleData`, allowing third-party modules to attach typed extension attributes via `withExtension()` without subclassing the DTO. See [markommerce/layout](/docs/packages/layout/) for details on the extension attribute pattern.

## Related Packages

- [markommerce/catalog](/docs/packages/catalog/) --- Provides `Product`, `Category`, and the repository and service layer consumed by this package
- [markommerce/catalog-storefront-scope](/docs/packages/catalog-storefront-scope/) --- Adds locale-aware rendering; Preference-replaces `ProductGridComponent` with `ScopedProductGridComponent`
- [markommerce/layout](/docs/packages/layout/) --- Layout resolution, typed component data DTOs, and extension operations used by the category page
- [markommerce/theme-blank](/docs/packages/theme-blank/) --- Provides `OneColumnLayout` extended by the category layout definition
- [markommerce/frontend](/docs/packages/frontend/) --- Frontend asset pipeline consumed by the storefront templates
