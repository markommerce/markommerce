# markommerce/tax-market

Market bridge for Markommerce tax — registers the tax-mode config key (`tax/prices_include_tax`) on the `market` axis for per-market overrides.

## Installation

```bash
composer require markommerce/tax-market
```

## Quick Example

This package boots a `ScopedFieldRegistry` registration so `TaxConfig::$pricesIncludeTax` becomes market-scopeable. Once installed alongside `markommerce/config-scope` and `markommerce/market`, per-market tax-mode overrides can be stored and resolved without any changes to the `tax` package:

```php
use Markommerce\ConfigScope\ScopedConfigWriter;
use Markommerce\Scope\Signature\ScopeSignature;

// Enable "prices include tax" for the US market only
$writer->setOverride('tax/prices_include_tax', new ScopeSignature(['market' => 'us']), true);

// Resolving under the US market context returns true; other markets fall back to the global value
$resolved = $resolver->resolved(TaxConfig::class, 'pricesIncludeTax');
```

## Documentation

Full usage, API reference, and examples: [markommerce/tax-market](https://markommerce.dev/docs/packages/tax-market/)
