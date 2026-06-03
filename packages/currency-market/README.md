# markommerce/currency-market

Market bridge for Markommerce currency — registers the `currency/base` config key on the `market` axis for per-market base currency overrides.

## Installation

```bash
composer require markommerce/currency-market
```

## Quick Example

This package boots a `ScopedFieldRegistry` registration so `CurrencyConfig::$base` becomes market-scopeable. Once installed alongside `markommerce/config-scope` and `markommerce/market`, per-market base currency overrides can be stored and resolved without any changes to the `currency` package:

```php
use Markommerce\ConfigScope\ScopedConfigWriter;
use Markommerce\Scope\Signature\ScopeSignature;

// Set EUR as the base currency for the US market only
$writer->setOverride('currency/base', new ScopeSignature(['market' => 'us']), 'EUR');

// Resolving under the US market context returns 'EUR'; other markets fall back to the global value
$resolved = $resolver->resolved(CurrencyConfig::class, 'base');
```

## Documentation

Full usage, API reference, and examples: [markommerce/currency-market](https://markommerce.dev/docs/packages/currency-market/)
