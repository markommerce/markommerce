---
title: markommerce/tax-market
description: Market bridge for Markommerce tax — registers the tax/prices_include_tax config key on the market axis for per-market overrides.
---

Market bridge for Markommerce tax. `markommerce/tax-market` registers `TaxConfig::$pricesIncludeTax` (`tax/prices_include_tax`) on the `market` axis via `ScopedFieldRegistry`. Once installed alongside `markommerce/config-scope` and `markommerce/market`, per-market tax mode overrides can be stored and resolved without any changes to the `tax` package.

## Installation

```bash
composer require markommerce/tax-market
```

This package requires `markommerce/tax`, `markommerce/config-scope`, and `markommerce/market`. All are pulled in automatically as Composer dependencies.

## Usage

### Writing a per-market tax mode override

Use `ScopedConfigWriter` (from `markommerce/config-scope`) to store a market-specific tax mode flag:

```php
use Markommerce\ConfigScope\ScopedConfigWriter;
use Markommerce\Scope\Signature\ScopeSignature;

// Enable "prices include tax" for the EU market only
$scopedConfigWriter->setOverride('tax/prices_include_tax', new ScopeSignature(['market' => 'eu']), true);

// Other markets fall back to the global value (e.g. false — exclusive)
```

### Resolving the market-scoped tax mode

No code change is needed in `TaxModeResolver`. When `markommerce/config-scope` is active, `ScopedConfigResolver` is bound as the active `ConfigResolver` via a Preference, and it honors the active market scope automatically:

```php
use Markommerce\Tax\TaxMode;
use Markommerce\Tax\TaxModeResolver;
use Markommerce\Scope\Context\ScopeContext;

// Set the active market context
$scopeContext->in('market', 'eu');

// TaxModeResolver::mode() now returns the EU-scoped override
$mode = $taxModeResolver->mode();
// => TaxMode::Inclusive
```

## How It Works

The package's `module.php` boot closure registers the scoped field once at module load time:

```php title="packages/tax-market/module.php"
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Tax\Config\TaxConfig;

'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
    $scopedFieldRegistry->register(
        entityClass: TaxConfig::class,
        property: 'pricesIncludeTax',
        axes: ['market'],
    );
},
```

This is a pure bridge --- the package adds no new services, repositories, or entities of its own.

## Related Packages

- [markommerce/tax](/docs/packages/tax/) --- `TaxConfig`, `TaxMode`, and `TaxModeResolver`
- [markommerce/config-scope](/docs/packages/config-scope/) --- `ScopedConfigWriter` and `ScopedConfigResolver`
- [markommerce/market](/docs/packages/market/) --- Declares the `market` scope axis
- [markommerce/scope](/docs/packages/scope/) --- `ScopedFieldRegistry` and resolution engine
