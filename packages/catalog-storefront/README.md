# markommerce/catalog-storefront

Public storefront for `markommerce/catalog` --- HTTP controllers, Latte templates, layout glue, and theme integration.

## Installation

```bash
composer require markommerce/catalog-storefront
```

`markommerce/catalog` is declared as a dependency and installed automatically. To add locale-aware rendering, also install `markommerce/catalog-storefront-scope`.

## Quick Example

After installation, the storefront category route is available immediately:

```bash
GET /catalog/category/{id}
```

```php
<?php

declare(strict_types=1);

use Markommerce\CatalogStorefront\Component\ProductGridComponent;
use Markommerce\Catalog\Entity\Category;

// ProductGridComponent builds the data bag for the category grid view
$data = $productGridComponent->data($category);

// $data->products — list of Product entities in the category
// $data->resolvedNames — map of product id => display name
// $data->resolvedDescs — map of product id => display description
```

## Storefront Route

`GET /catalog/category/{id}` --- returns the category page with its product grid. Registered automatically by the module via the `CategoryController`.

The route resolves the category by `id`, renders the product grid using `ProductGridComponent`, and passes a `ProductGridData` value object to the Latte template.

## Layout

The module registers a layout definition that wraps category pages in the `theme-blank` base layout. To swap themes, replace the layout definition using Marko's module system or supply your own theme package.

```php title="module.php"
use Markommerce\CatalogStorefront\Component\ProductGridComponent;
use Markommerce\Layout\LayoutDefinition;

// Override the default layout by binding your own LayoutDefinition
// in your module's boot closure — no forking required.
```

## Components

| Component | Description |
|-----------|-------------|
| `ProductGridComponent` | Resolves products for a category and builds `ProductGridData` |
| `ProductCard` | Renders a single product card (name, description, stock badge) |
| `StockBadge` | Displays stock status for a product |

`ProductGridData` fields:

| Field | Type | Description |
|-------|------|-------------|
| `category` | `Category` | The resolved category entity |
| `products` | `list<Product>` | Products assigned to the category |
| `resolvedNames` | `array<int, string>` | Display name per product id |
| `resolvedDescs` | `array<int, string\|null>` | Display description per product id |

For locale-aware name and description resolution, install `markommerce/catalog-storefront-scope`. That package Preference-replaces `ProductGridComponent` with `ScopedProductGridComponent`, which resolves field values through `ScopeResolver`.

## Documentation

Full usage, API reference, and examples: [markommerce/catalog-storefront](https://markommerce.dev/docs/packages/catalog-storefront/)
