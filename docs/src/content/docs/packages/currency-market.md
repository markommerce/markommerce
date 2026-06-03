---
title: markommerce/currency-market
description: Market bridge for Markommerce currency — registers the currency/base config key on the market axis for per-market base currency overrides.
---

Market bridge for Markommerce currency. `markommerce/currency-market` registers `CurrencyConfig::$base` (`currency/base`) on the `market` axis via `ScopedFieldRegistry`. Once installed alongside `markommerce/config-scope` and `markommerce/market`, per-market base currency overrides can be stored and resolved without any changes to the `currency` package.

## Installation

```bash
composer require markommerce/currency-market
```

This package requires `markommerce/currency`, `markommerce/config-scope`, and `markommerce/market`. All are pulled in automatically as Composer dependencies.

## Usage

### Writing a per-market currency override

Use `ScopedConfigWriter` (from `markommerce/config-scope`) to store a market-specific currency code:

```php
use Markommerce\ConfigScope\ScopedConfigWriter;
use Markommerce\Scope\Signature\ScopeSignature;

// Set EUR as the base currency for the EU market
$scopedConfigWriter->setOverride('currency/base', new ScopeSignature(['market' => 'eu']), 'EUR');

// Other markets fall back to the global value (e.g. 'USD')
```

### Resolving the market-scoped currency

No code change is needed in `CurrencyResolver`. When `markommerce/config-scope` is active, `ScopedConfigResolver` is bound as the active `ConfigResolver` via a Preference, and it honors the active market scope automatically:

```php
use Markommerce\Currency\CurrencyResolver;
use Markommerce\Scope\Context\ScopeContext;

// Set the active market context
$scopeContext->in('market', 'eu');

// CurrencyResolver::base() now returns the EU-scoped override
$currency = $currencyResolver->base();
// => Currency { code: 'EUR', ... }
```

## How It Works

The package's `module.php` boot closure registers the scoped field once at module load time:

```php title="packages/currency-market/module.php"
use Markommerce\Currency\Config\CurrencyConfig;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;

'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
    $scopedFieldRegistry->register(
        entityClass: CurrencyConfig::class,
        property: 'base',
        axes: ['market'],
    );
},
```

This is a pure bridge --- the package adds no new services, repositories, or entities of its own.

## Related Packages

- [markommerce/currency](/docs/packages/currency/) --- `CurrencyConfig` and `CurrencyResolver`
- [markommerce/config-scope](/docs/packages/config-scope/) --- `ScopedConfigWriter` and `ScopedConfigResolver`
- [markommerce/market](/docs/packages/market/) --- Declares the `market` scope axis
- [markommerce/scope](/docs/packages/scope/) --- `ScopedFieldRegistry` and resolution engine
