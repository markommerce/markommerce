---
title: markommerce/config-locale
description: Locale-aware config resolution for Markommerce — bridges markommerce/config-scope and markommerce/locale to resolve configuration values per locale.
---

Locale-aware config resolution for Markommerce. `markommerce/config-locale` bridges `markommerce/config-scope` and `markommerce/locale` so that config properties marked `#[Scoped(axes: ['locale'])]` are automatically resolved against the active locale without any extra wiring.

## Installation

```bash
composer require markommerce/config-locale
```

## Placeholder Status

This package is a **placeholder bridge**. The `boot` closure currently registers no scoped config fields --- locale-scoped config resolution is not yet implemented. See the planned end state in `FEATURES.md`.

When fully implemented, `config-locale` will register the `locale` axis for config properties at boot time, making locale-scoped config resolution available automatically for any property annotated with `#[Scoped(axes: ['locale'])]`.

## How it will work

Once implemented, this package will follow the same bridge pattern used by `markommerce/catalog-locale`: a `boot` callback in `module.php` registers config properties with `ScopedFieldRegistry`. The `locale` axis will be automatically active for any config property carrying the locale axis declaration.

Example of what locale-scoped config resolution will look like:

```php
<?php

declare(strict_types=1);

use Markommerce\Config\Attributes\Config;
use Markommerce\Scope\Attributes\Scoped;

class ShopConfig
{
    #[Config(key: 'shop/display.welcome_message')]
    #[Scoped(axes: ['locale'])]
    public string $welcomeMessage = 'Welcome!';
}

// After setting the active locale context:
// $scopeContext->in('locale', 'de');
// $cfg->welcomeMessage resolves to the German override
```

## Related Packages

- [markommerce/config-scope](/docs/packages/config-scope/) --- Core scope-aware config package providing `ScopedConfigResolver` and `ScopedConfigWriterInterface`
- [markommerce/locale](/docs/packages/locale/) --- Declares the `locale` axis in `config/scope.php`
- [markommerce/config](/docs/packages/config/) --- Base config package: `#[Config]`, `ConfigResolver`, `ConfigWriterInterface`
