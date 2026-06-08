---
title: markommerce/catalog-storefront-scope
description: Locale-aware product grid rendering for markommerce/catalog-storefront --- Preference-replaces ProductGridComponent with ScopedProductGridComponent.
---

Locale-aware product grid rendering for `markommerce/catalog-storefront`. `markommerce/catalog-storefront-scope` Preference-replaces the default `ProductGridComponent` with `ScopedProductGridComponent`, which resolves `Product.name` and `Product.description` through `ScopeResolver` before returning `ProductGridData`. Installing the package and running Marko's module system is sufficient --- no merchant configuration is required. The active locale scope is picked up automatically from the request context.

## Installation

```bash
composer require markommerce/catalog-storefront-scope
```

Both `markommerce/catalog-storefront` and `markommerce/catalog-scope` are declared as Composer dependencies and installed automatically. To enable locale-aware field registration, also install [markommerce/catalog-locale](/docs/packages/catalog-locale/), which registers `Product.name` and `Product.description` as locale-scoped fields via `ScopedFieldRegistry`.

## Usage

### Auto-wiring via Preference

`ScopedProductGridComponent` is declared with `#[Preference(replaces: ProductGridComponent::class)]`. When this package is installed, Marko's container automatically resolves `ScopedProductGridComponent` wherever `ProductGridComponent` is requested. No manual wiring is required:

```php
<?php

declare(strict_types=1);

use Marko\Core\Attributes\Preference;
use Markommerce\Catalog\Pagination\PaginationOptionsResolver;
use Markommerce\Catalog\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface;
use Markommerce\CatalogStorefront\Component\ProductGridComponent;
use Markommerce\Currency\CurrencyResolver;
use Markommerce\MoneyIntl\MoneyFormatter;
use Markommerce\Scope\Resolver\ScopeResolver;

#[Preference(replaces: ProductGridComponent::class)]
class ScopedProductGridComponent extends ProductGridComponent
{
    public function __construct(
        CategoryAssignmentService $categoryAssignmentService,
        PaginationOptionsResolver $paginationOptionsResolver,
        private ScopeResolver $scopeResolver,
        PriceResolverInterface $priceResolver,
        MoneyFormatter $moneyFormatter,
        ProductPriceIndexRepositoryInterface $productPriceIndexRepository,
        CurrencyResolver $currencyResolver,
    ) {
        parent::__construct(
            $categoryAssignmentService,
            $paginationOptionsResolver,
            $priceResolver,
            $moneyFormatter,
            $productPriceIndexRepository,
            $currencyResolver,
        );
    }
}
```

The override extends `ProductGridComponent` and overrides `data()` to run each product's `name` and `description` fields through `ScopeResolver`, picking up the active locale scope set by the request lifecycle.

### Full bridge stack

For a complete locale-aware storefront, install the full bridge stack:

```bash
composer require markommerce/catalog-storefront-scope
composer require markommerce/catalog-locale
composer require markommerce/scope-pgsql
```

This pulls in:
- `markommerce/catalog-storefront` --- the storefront route, layout, and base components
- `markommerce/catalog-scope` --- the `scopes` JSON column on `Product` and `Category`
- `markommerce/catalog-locale` --- field registration bridge that declares `name` and `description` as locale-scoped
- `markommerce/locale` --- the `locale` axis declaration

With the full stack installed and booted, the storefront automatically renders locale-resolved product names and descriptions for the active locale.

### No merchant configuration required

The Preference is auto-discovered by Marko's module system. Merchants do not need to configure anything --- the locale-aware component activates as soon as the package is installed and the modules are loaded.

See [markommerce/catalog-locale](/docs/packages/catalog-locale/) for the field registration bridge pattern, and [markommerce/catalog-scope](/docs/packages/catalog-scope/) for the storage layer details.

## API Reference

### `ScopedProductGridComponent`

`#[Preference(replaces: ProductGridComponent::class)]`

Extends `ProductGridComponent`. Overrides `data()` to resolve locale-aware names and descriptions via `ScopeResolver`.

| Method | Return type | Description |
|---|---|---|
| `data(Category $category)` | `ProductGridData` | Returns product grid data with locale-resolved `resolvedNames` and `resolvedDescs` maps, replacing the raw values from the base component. |

The returned `ProductGridData` is structurally identical to the one produced by the base `ProductGridComponent` --- only the values in `resolvedNames` and `resolvedDescs` differ, reflecting locale overrides stored in the `scopes` column.

## Related Packages

- [markommerce/catalog-storefront](/docs/packages/catalog-storefront/) --- Provides `ProductGridComponent` and the storefront route that this package extends
- [markommerce/catalog-scope](/docs/packages/catalog-scope/) --- Provides `ProductScopedOverrides` and `ScopeResolver` used for field resolution
- [markommerce/catalog-locale](/docs/packages/catalog-locale/) --- Bridge that registers catalog fields as locale-scoped via `ScopedFieldRegistry`
- [markommerce/scope](/docs/packages/scope/) --- Resolution engine and `ScopeResolver`
- [markommerce/scope-pgsql](/docs/packages/scope-pgsql/) --- PostgreSQL driver required to persist and query scoped overrides
