# markommerce/catalog-storefront-scope

Locale-aware product grid rendering for `markommerce/catalog-storefront` --- Preference-replaces the default `ProductGridComponent` with `ScopedProductGridComponent`, which resolves `Product.name` and `Product.description` through `ScopeResolver`.

## Installation

```bash
composer require markommerce/catalog-storefront-scope
```

Both `markommerce/catalog-storefront` and `markommerce/catalog-scope` are declared as dependencies and installed automatically.

## Quick Example

Install on top of the bridge stack --- no further configuration is required. The `Preference` is auto-discovered and replaces `ProductGridComponent` transparently:

```php
<?php

declare(strict_types=1);

use Markommerce\CatalogStorefrontScope\Component\ScopedProductGridComponent;

// ScopedProductGridComponent is injected wherever ProductGridComponent is requested.
// It delegates to the parent for the product list, then resolves locale-aware
// names and descriptions via ScopeResolver before returning ProductGridData.
```

## How It Works

`ScopedProductGridComponent` is decorated with `#[Preference(replaces: ProductGridComponent::class)]`, so Marko's container automatically substitutes it for the default grid component. It extends `ProductGridComponent` and overrides `data()` to run each product's `name` and `description` fields through `ScopeResolver`, picking up the active locale scope.

This package depends on both `markommerce/catalog-storefront` (for `ProductGridComponent` and `ProductGridData`) and `markommerce/catalog-scope` (for `ProductScopedOverrides` and `ScopeResolver`).

## Documentation

Full usage, API reference, and examples: [markommerce/catalog-storefront-scope](https://markommerce.dev/docs/packages/catalog-storefront-scope/)
