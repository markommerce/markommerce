---
title: markommerce/locale
description: Locale axis declaration for Markommerce --- contributes the locale axis to scope.axes.
---

Locale axis declaration for Markommerce. `markommerce/locale` contributes the `locale` axis to the scope configuration, making it available for scoped field registration and scope resolution via `markommerce/scope`. The package ships a `config/scope.php` that merges the `locale` axis into the scope configuration when loaded alongside `markommerce/scope`. There are no PHP source classes --- the package is configuration only. Future locale-specific helpers (language detection, display names, currency formatting) will live here from P5 onwards.

## Installation

```bash
composer require markommerce/locale
```

This package requires `markommerce/scope`.

## Configuration

The package ships a minimal `config/scope.php` that declares the `locale` axis with a single `default` scope as a starting point. Extend this in your application's `config/scope.php` with the locale paths your store supports:

```php title="config/scope.php"
<?php

declare(strict_types=1);

return [
    'axes' => [
        'locale' => [
            'default' => 'default',
            'scopes'  => [
                'default' => [],
                'en'      => [],
                'de'      => [],
                'de-DE'   => [],
                'de-AT'   => [],
                'fr'      => [],
                'fr-FR'   => [],
                'fr-BE'   => [],
            ],
        ],
    ],
];
```

After installation the `locale` axis is available for scoped field registration and scope resolution via `markommerce/scope`. The scope hierarchy supports dot-notation paths (e.g. `de-DE` walks up to `de`) --- see [markommerce/scope](/docs/packages/scope/) for the full hierarchy and resolver chain configuration.

## Usage

Once the `locale` axis is available, activate a locale for the current request using `ScopeContext`:

```php
<?php

declare(strict_types=1);

use Markommerce\Scope\Context\ScopeContext;

$scopeContext->in('locale', 'de-DE');
```

From that point, any call to `ScopeResolver::resolved()` on a locale-scoped property will walk the `de-DE → de` hierarchy and return the most specific override found.

To wire locale-scoped fields onto catalog entities, install `markommerce/catalog-locale`. That package's `module.php` boot closure registers `Product.name`, `Product.description`, `Category.name`, and `Category.description` as locale-scoped via `ScopedFieldRegistry` --- no manual field registration is needed.

## Related Packages

- [markommerce/scope](/docs/packages/scope/) --- Scope resolution engine; `locale` is one axis within the multi-axis system
- [markommerce/catalog-locale](/docs/packages/catalog-locale/) --- Bridge that registers catalog fields as locale-scoped and serves as the canonical bridge example
