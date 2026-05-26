---
title: markommerce/catalog-locale
description: Locale field bridge for catalog entities --- registers Product and Category fields as locale-scoped via ScopedFieldRegistry at boot.
---

Locale field bridge for catalog entities. `markommerce/catalog-locale` is a thin auto-wiring package whose entire purpose is a `boot` closure in `module.php` that registers `Product.name`, `Product.description`, `Category.name`, and `Category.description` as locale-scoped fields via `ScopedFieldRegistry`. Installing the package is the configuration --- the boot closure runs automatically when Marko loads the module, and no manual field registration is needed.

## Installation

```bash
composer require markommerce/catalog-locale
```

This package requires both `markommerce/catalog-scope` (storage layer) and `markommerce/locale` (axis declaration). Both are pulled in automatically as Composer dependencies.

## Usage

### Auto-wiring via module.php

The package declares its dependencies and boot logic entirely in `module.php`:

```php title="packages/catalog-locale/module.php"
<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;

return [
    'require' => [
        'markommerce/catalog-scope' => '*',
        'markommerce/locale' => '*',
    ],
    'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
        foreach ([Product::class, Category::class] as $entityClass) {
            foreach (['name', 'description'] as $property) {
                $scopedFieldRegistry->register(
                    entityClass: $entityClass,
                    property: $property,
                    axes: ['locale'],
                );
            }
        }
    },
];
```

The `ScopedFieldRegistry` is injected by type-hint from the container. The `boot` closure runs during the module bootstrap phase, before any request handling begins --- this is the required timing so that the registry cache is never stale (see [markommerce/scope](/docs/packages/scope/) for the cache-staleness contract).

After the boot closure runs, `ScopeResolver` knows that `Product.name`, `Product.description`, `Category.name`, and `Category.description` are locale-scoped and will look up overrides in the `scopes` JSON column when resolving values.

### Resolving locale-scoped catalog fields

Once this package is installed and booted alongside `markommerce/scope` and `markommerce/locale`, locale-aware resolution works out of the box:

```php
<?php

declare(strict_types=1);

use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Resolver\ScopeResolver;

// Set the active locale for the current request
$scopeContext->in('locale', 'de');

// ScopeResolver resolves the locale-scoped name for this product
$resolvedName = $scopeResolver->resolved($product, 'name'); // German override, or raw name if none set
```

### Writing a custom bridge

`catalog-locale` is the canonical example of a bridge package. You can follow the same pattern to add locale-scoped fields to any entity --- for example, a custom `Product.subtitle` field added by your application:

```php title="app/module.php"
<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Product;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;

return [
    'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
        $scopedFieldRegistry->register(
            entityClass: Product::class,
            property: 'subtitle',
            axes: ['locale'],
        );
    },
];
```

The same pattern works for adding additional axes. To add a `channel`-scoped `price` field to `Product`:

```php title="app/module.php"
<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Product;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;

return [
    'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
        $scopedFieldRegistry->register(
            entityClass: Product::class,
            property: 'price',
            axes: ['channel', 'locale'],
        );
    },
];
```

Multiple `register()` calls for the same property are merged (union, no duplicates) --- you can safely call `register()` for the same class and property in separate modules without conflict.

## API Reference

This package has no PHP classes. Its entire public surface is the `module.php` boot closure and the side effect it produces: four entries in `ScopedFieldRegistry`.

| Field registered | Entity | Axes |
|-----------------|--------|------|
| `name` | `Product` | `['locale']` |
| `description` | `Product` | `['locale']` |
| `name` | `Category` | `['locale']` |
| `description` | `Category` | `['locale']` |

See [markommerce/scope --- ScopedFieldRegistry](/docs/packages/scope/#scopedfieldregistry) for the full `register()` API.

## Related Packages

- [markommerce/catalog-scope](/docs/packages/catalog-scope/) --- Provides the `scopes` column on catalog entities; required by this package
- [markommerce/locale](/docs/packages/locale/) --- Declares the `locale` axis; required by this package
- [markommerce/scope](/docs/packages/scope/) --- `ScopedFieldRegistry` and resolution engine
- [markommerce/catalog](/docs/packages/catalog/) --- `Product` and `Category` entities whose fields are registered by this bridge
