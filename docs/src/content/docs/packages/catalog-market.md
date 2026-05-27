---
title: markommerce/catalog-market
description: Market field bridge for catalog entities --- placeholder bridge that reserves the ScopedFieldRegistry hook for future price and visibility axes.
---

Market field bridge for catalog entities. `markommerce/catalog-market` is a **placeholder** package whose `module.php` boot closure is intentionally empty today. It reserves the `ScopedFieldRegistry` hook for future market-scoped fields --- most notably `price` and `visibility` --- once those columns land on the `Product` entity. Installing the package declares the dependency relationship between `markommerce/catalog-scope` and `markommerce/market` without registering any fields yet.

## Installation

```bash
composer require markommerce/catalog-market
```

This package requires both `markommerce/catalog-scope` (storage layer) and `markommerce/market` (axis declaration). Both are pulled in automatically as Composer dependencies.

## Placeholder Status

The `boot` closure in `module.php` is type-hinted on `ScopedFieldRegistry` but does not register any fields:

```php title="packages/catalog-market/module.php"
<?php

declare(strict_types=1);

use Markommerce\Scope\Metadata\ScopedFieldRegistry;

return [
    'require' => [
        'markommerce/catalog-scope' => '*',
        'markommerce/market' => '*',
    ],
    'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
        // No fields registered yet — Product does not have price or visibility columns.
        // This closure is type-hinted on ScopedFieldRegistry for future expansion.
    },
];
```

The planned fields are `price` and `visibility` on `Product`. Once those columns are added to the `catalog_products` table, this bridge will register them as market-scoped via `ScopedFieldRegistry`, following the same pattern as [markommerce/catalog-locale](/docs/packages/catalog-locale/).

## Uninstalling

Because this package registers no fields, it can be safely removed without any data migration. If you remove it before the price/visibility columns are added, no overrides will be lost.

## Related Packages

- [markommerce/catalog-scope](/docs/packages/catalog-scope/) --- Provides the `scopes` column on catalog entities; required by this package
- [markommerce/market](/docs/packages/market/) --- Declares the `market` axis; required by this package
- [markommerce/scope](/docs/packages/scope/) --- `ScopedFieldRegistry` and resolution engine
- [markommerce/catalog](/docs/packages/catalog/) --- `Product` and `Category` entities whose fields will be registered by this bridge in future
- [markommerce/catalog-market-category-trees](/docs/packages/catalog-market-category-trees/) --- Related market integration that assigns category trees per market
