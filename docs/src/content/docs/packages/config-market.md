---
title: markommerce/config-market
description: Market-aware config resolution for Markommerce — bridges markommerce/config-scope and markommerce/market to resolve configuration values per market.
---

Market-aware config resolution for Markommerce. `markommerce/config-market` bridges `markommerce/config-scope` and `markommerce/market` so that config properties marked `#[Scoped(axes: ['market'])]` are automatically resolved against the active market without any extra wiring.

## Installation

```bash
composer require markommerce/config-market
```

## Placeholder Status

This package is a **placeholder bridge**. The `boot` closure currently registers no scoped config fields --- market-scoped config resolution is not yet implemented. See the planned end state in `FEATURES.md`.

When fully implemented, `config-market` will register the `market` axis for config properties at boot time, making market-scoped config resolution available automatically for any property annotated with `#[Scoped(axes: ['market'])]`.

## How it will work

Once implemented, this package will follow the same bridge pattern used by `markommerce/catalog-locale`: a `boot` callback in `module.php` registers config properties with `ScopedFieldRegistry`. The `market` axis will be automatically active for any config property carrying the market axis declaration.

Example of what market-scoped config resolution will look like:

```php
<?php

declare(strict_types=1);

use Markommerce\Config\Attributes\Config;
use Markommerce\Scope\Attributes\Scoped;

class PricingConfig
{
    #[Config(key: 'pricing/display.currency')]
    #[Scoped(axes: ['market'])]
    public string $currency = 'USD';
}

// After setting the active market context:
// $scopeContext->in('market', 'eu.de');
// $cfg->currency resolves to the German market override (e.g. 'EUR')
```

## Related Packages

- [markommerce/config-scope](/docs/packages/config-scope/) --- Core scope-aware config package providing `ScopedConfigResolver` and `ScopedConfigWriterInterface`
- [markommerce/market](/docs/packages/market/) --- Declares the `market` axis in `config/scope.php`
- [markommerce/config](/docs/packages/config/) --- Base config package: `#[Config]`, `ConfigResolver`, `ConfigWriterInterface`
