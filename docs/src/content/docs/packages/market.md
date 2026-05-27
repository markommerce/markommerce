---
title: markommerce/market
description: Market axis declaration for Markommerce --- contributes the market axis to scope.axes.
---

Market axis declaration for Markommerce. `markommerce/market` contributes the `market` axis to the scope configuration, making it available for scoped field registration and scope resolution via `markommerce/scope`. The package ships a `config/scope.php` that merges the `market` axis into the scope configuration when loaded alongside `markommerce/scope`. There are no PHP source classes --- the package is configuration only. Future market-specific helpers (market detection, currency mapping, price resolution) will live here from P5 onwards.

## Installation

```bash
composer require markommerce/market
```

This package requires `markommerce/scope`.

## Configuration

The package ships a minimal `config/scope.php` that declares the `market` axis with a single `default` scope as a starting point. Extend this in your application's `config/scope.php` with the market paths your store supports:

```php title="config/scope.php"
<?php

declare(strict_types=1);

return [
    'axes' => [
        'market' => [
            'default' => 'default',
            'scopes'  => [
                'default' => [],
                'eu'      => [],
                'eu-de'   => [],
                'eu-fr'   => [],
                'us'      => [],
            ],
        ],
    ],
];
```

After installation the `market` axis is available for scoped field registration and scope resolution via `markommerce/scope`. The scope hierarchy supports dot-notation paths (e.g. `eu-de` walks up to `eu`) --- see [markommerce/scope](/docs/packages/scope/) for the full hierarchy and resolver chain configuration.

## Usage

Once the `market` axis is available, activate a market for the current request using `ScopeContext`:

```php
<?php

declare(strict_types=1);

use Markommerce\Scope\Context\ScopeContext;

$scopeContext->in('market', 'eu-de');
```

From that point, any call to `ScopeResolver::resolved()` on a market-scoped property will walk the `eu-de → eu` hierarchy and return the most specific override found.

To assign per-market category trees to the catalog, install `markommerce/catalog-market-category-trees`. That package provides `CategoryTreeMarketResolver` and `CategoryTreeMarketAssignmentService` for routing each market to its dedicated category tree, with automatic fallback to the default tree.

## Related Packages

- [markommerce/scope](/docs/packages/scope/) --- Scope resolution engine; `market` is one axis within the multi-axis system
- [markommerce/catalog-market](/docs/packages/catalog-market/) --- Placeholder bridge that reserves the `ScopedFieldRegistry` hook for future market-scoped fields on catalog entities
- [markommerce/catalog-market-category-trees](/docs/packages/catalog-market-category-trees/) --- Assigns per-market category trees and resolves the active tree for a market
