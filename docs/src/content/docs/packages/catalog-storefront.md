---
title: markommerce/catalog-storefront
description: Public storefront for markommerce/catalog --- HTTP controllers, Latte templates, layout glue, and theme integration.
---

Public storefront for `markommerce/catalog`. `markommerce/catalog-storefront` provides the `CategoryController`, layout definition, `ProductGridComponent`, `ProductCard`, `StockBadge`, and Latte templates that turn the catalog domain into a browsable storefront. Installing the package is enough to get a working product grid at `GET /catalog/category/{id}` --- no manual wiring required. The product card resolves and displays a locale-formatted price using [markommerce/pricing](/docs/packages/pricing/) and [markommerce/money-intl](/docs/packages/money-intl/); when a product has no price the card omits the price element. To add locale-aware name and description resolution, install [markommerce/catalog-storefront-scope](/docs/packages/catalog-storefront-scope/).

## Installation

```bash
composer require markommerce/catalog-storefront
```

`markommerce/catalog`, `markommerce/catalog-price-index`, `markommerce/currency`, `markommerce/pricing`, and `markommerce/money-intl` are declared as Composer dependencies and installed automatically. The package registers its module bindings and layout definition via `module.php`. No manual service binding is required.

## Usage

### Storefront routes

The module registers the following routes automatically via `CategoryController`:

```
GET /catalog/category/{id}
GET /catalog/category/{id}/page
```

`CategoryController::show()` performs a category lookup by `id`, resolves pagination options from the `page`, `size`, and `sort` query parameters, and returns a `404` when the category does not exist. Page numbers exceeding `maxPageDepth` return `410 Gone`. When the `sort` parameter refers to a key that is not registered or is excluded by `enabledSorts`, the controller issues a **302 redirect** to the same category URL with the `sort` parameter removed (preserving `page` if `> 1` and `size` if `> 0`), so the page renders with the default sort order rather than returning an error. The response includes a `Link: <url>; rel="canonical"` header that normalises redundant query parameters and points small categories (below `viewAllThreshold`) to their `?view=all` URL.

`CategoryController::pageFragment()` at `GET /catalog/category/{id}/page` is a server-rendered fragment endpoint consumed by the `load_more` and `infinite` presentation modes. It accepts the same `page`, `size`, and `sort` parameters and also returns `410 Gone` when the page depth cap is exceeded. An unknown `sort` value issues the same **302 redirect** as `show()`.

Both routes are rendered by `markommerce/layout` --- `CategoryController` carries no `#[Layout]` attribute; placement is declared entirely in the layout definition files.

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
| `show(int $id, Request $request)` | `GET /catalog/category/{id}` | Resolve the category by `id`, resolve pagination from `page`/`size`/`sort` query params, and render the product grid page. Returns `404` when the category does not exist; `410` when the page number exceeds `maxPageDepth`; **302** when `sort` is unknown or disabled (redirects to the same URL without `sort`). Sets a `Link: rel=canonical` response header. |
| `pageFragment(int $id, Request $request)` | `GET /catalog/category/{id}/page` | Server-rendered page fragment for `load_more` and `infinite` presentation modes. Returns `404` for unknown categories; `410` for depth cap violations; **302** when `sort` is unknown or disabled. |

### `ProductGridComponent`

A placement-agnostic component that resolves a paginated product page for a category and builds `ProductGridData`. Its `data()` method delegates to `PaginationOptionsResolver` to translate request parameters into a `ResolvedPaginationOptions`, then calls `CategoryAssignmentService::paginatedProductsInCategory()`. It populates `resolvedNames` and `resolvedDescs` with the raw entity values and fills the pagination fields (`currentPage`, `totalPages`, `hasNext`, `hasPrevious`, `pageLinkUrls`, `nextPageUrl`) from the returned `Page`. The component also reads all registered sort orders from `CategorySortOrderRegistry` and exposes them as `sortOptions` (a list of `{key, label}` maps) together with `activeSort` (the key of the currently active sort order), which the Latte template uses to render a sort dropdown.

For prices, the component first batch-loads index entries for all product IDs on the current page via `ProductPriceIndexRepositoryInterface::findByProductIds()` --- one query per page regardless of page size. Products found in the index have their `amount` wrapped in a `Money` object using the base currency from `CurrencyResolver` and formatted by `MoneyFormatter`. Products not yet present in the index fall back to `PriceResolverInterface` per product. Products with no resolvable price receive a `null` entry in `formattedPrices`.

When [markommerce/catalog-storefront-scope](/docs/packages/catalog-storefront-scope/) is installed, its `ScopedProductGridComponent` Preference replaces this component and resolves locale-aware names and descriptions via `ScopeResolver`.

| Method | Return type | Description |
|---|---|---|
| `data(Category $category, int $page, int $size, string $sort)` | `ProductGridData` | Load a paginated product page for the category; return a `ProductGridData` DTO with raw name and description values, a formatted price map, sort dropdown data, and pagination metadata. |

### `ProductCard`

The per-item component rendered inside the `products` repeat slot. Its `data(Product $product)` method returns a `ProductCardData` DTO. The component injects `PriceResolverInterface` and `MoneyFormatter` to resolve and locale-format the product's price; a `null` `formattedPrice` is returned when the product has no price or the resolver throws `PriceUnavailableException`.

| Method | Return type | Description |
|---|---|---|
| `data(Product $product)` | `ProductCardData` | Build per-product display data including resolved name, description, stock status, and locale-formatted price (nullable). |

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
| `$products` | `list<Product>` | Products for the current page |
| `$resolvedNames` | `array<int, string>` | Display name keyed by product ID (raw value; scope-resolved when `catalog-storefront-scope` is installed) |
| `$resolvedDescs` | `array<int, string\|null>` | Display description keyed by product ID (raw value; scope-resolved when `catalog-storefront-scope` is installed) |
| `$formattedPrices` | `array<int, string\|null>` | Locale-formatted price string keyed by product ID; `null` when the product has no price |
| `$presentation` | `PaginationPresentation` | Active storefront presentation mode (`numbered`, `load_more`, or `infinite`) |
| `$currentPage` | `?int` | Current page number (1-based); `null` when the offset strategy is not active |
| `$totalPages` | `?int` | Total page count; `null` when the offset strategy is not active |
| `$hasNext` | `bool` | Whether a next page exists |
| `$hasPrevious` | `bool` | Whether a previous page exists |
| `$pageLinkUrls` | `list<string>` | Crawlable numbered page URLs (e.g. `['?page=1', '?page=2', ...]`); populated only for `numbered` presentation |
| `$nextPageUrl` | `?string` | URL for the next page; a `?page=N` query string for offset or a `?position=TOKEN` for keyset; `null` on the last page |
| `$sortOptions` | `list<array{key: string, label: string}>` | All sort orders registered in `CategorySortOrderRegistry`, in priority order; used to render the sort dropdown |
| `$activeSort` | `string` | Key of the currently active sort order (e.g. `'position'`, `'price_asc'`) |
| `$extensions` | `ExtensionBag` | Typed extension attributes (third-party use) |

### `ProductCardData`

DTO returned by `ProductCard::data()`. Extends `ExtensibleData`.

| Property | Type | Description |
|---|---|---|
| `$product` | `Product` | The product entity |
| `$resolvedName` | `string` | Display name (raw value; scope-resolved when `catalog-storefront-scope` is installed) |
| `$resolvedDesc` | `string` | Display description, defaults to empty string when the product has no description (raw value; scope-resolved when `catalog-storefront-scope` is installed) |
| `$inStock` | `bool` | Whether the product is currently in stock |
| `$formattedPrice` | `?string` | Locale-formatted price string; `null` when the product has no price |
| `$extensions` | `ExtensionBag` | Typed extension attributes (third-party use) |

Both `ProductGridData` and `ProductCardData` extend `ExtensibleData`, allowing third-party modules to attach typed extension attributes via `withExtension()` without subclassing the DTO. See [markommerce/layout](/docs/packages/layout/) for details on the extension attribute pattern.

## Related Packages

- [markommerce/catalog](/docs/packages/catalog/) --- Provides `Product`, `Category`, the repository and service layer, and `PaginationOptionsResolver` consumed by this package
- [markommerce/criteria](/docs/packages/criteria/) --- Pagination engine; `ProductGridComponent` works with the `Page` and `RandomAccessPageInterface` types it defines
- [markommerce/catalog-price-index](/docs/packages/catalog-price-index/) --- Provides `ProductPriceIndexRepositoryInterface`; `ProductGridComponent` batch-loads prices from this index, falling back to `PriceResolverInterface` for products not yet indexed
- [markommerce/currency](/docs/packages/currency/) --- Provides `CurrencyResolver`, used to determine the base currency when constructing `Money` values from index entries
- [markommerce/catalog-storefront-scope](/docs/packages/catalog-storefront-scope/) --- Adds locale-aware rendering; Preference-replaces `ProductGridComponent` with `ScopedProductGridComponent`
- [markommerce/pricing](/docs/packages/pricing/) --- Resolves a product's effective price as a `Money` value object; used as a fallback by `ProductGridComponent` and by `ProductCard`
- [markommerce/money-intl](/docs/packages/money-intl/) --- Provides `MoneyFormatter`, which locale-formats `Money` values into display strings
- [markommerce/layout](/docs/packages/layout/) --- Layout resolution, typed component data DTOs, and extension operations used by the category page
- [markommerce/theme-blank](/docs/packages/theme-blank/) --- Provides `OneColumnLayout` extended by the category layout definition
- [markommerce/frontend](/docs/packages/frontend/) --- Frontend asset pipeline consumed by the storefront templates
