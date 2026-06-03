---
title: markommerce/tax
description: Tax mode configuration for Markommerce — globally configurable tax-inclusive/exclusive pricing mode with no rate computation.
---

Tax mode configuration for Markommerce. `markommerce/tax` ships a `TaxConfig` class with a `tax/prices_include_tax` config key and a `TaxModeResolver` that reads the flag and returns the active `TaxMode` enum case. This package provides the mode flag only --- no rate computation, no tax amount calculation. Tax rates belong in a separate package.

Tax mode is resolved independently of the pricing pipeline --- `markommerce/pricing` has no dependency on `markommerce/tax`.

## Installation

```bash
composer require markommerce/tax
```

## Configuration

The tax-inclusive flag is controlled by the `tax/prices_include_tax` config key, declared in `TaxConfig`:

```php title="packages/tax/src/Config/TaxConfig.php"
use Markommerce\Config\Attributes\Config;

class TaxConfig
{
    #[Config(key: 'tax/prices_include_tax')]
    public bool $pricesIncludeTax = false;
}
```

The default is `false` (exclusive pricing). To switch to inclusive pricing, write `true` for that key via `ConfigWriterInterface::setGlobal('tax/prices_include_tax', true)`. After changing the class, regenerate the config proxy:

```bash
php marko config:generate
```

## Usage

### Resolving the active tax mode

Inject `TaxModeResolver` and call `mode()`. It returns a `TaxMode` enum case:

```php
use Markommerce\Tax\TaxMode;
use Markommerce\Tax\TaxModeResolver;

// $taxModeResolver is injected by the container
$mode = $taxModeResolver->mode(); // TaxMode::Exclusive (default) or TaxMode::Inclusive

if ($mode === TaxMode::Inclusive) {
    // Prices already contain tax — display as-is
} else {
    // Prices are exclusive of tax — add tax at display/checkout time
}
```

When `markommerce/config-scope` is active, `ScopedConfigResolver` is used automatically, so the mode can vary per scope without any code change.

### Per-market tax mode overrides

Install [markommerce/tax-market](/docs/packages/tax-market/) to make `tax/prices_include_tax` overridable per market. That bridge registers `TaxConfig::$pricesIncludeTax` on the `market` axis via `ScopedFieldRegistry`.

## API Reference

### `TaxConfig`

Config class declaring the tax-inclusive flag.

| Property | Config key | Default | Description |
|---|---|---|---|
| `$pricesIncludeTax` | `tax/prices_include_tax` | `false` | When `true`, stored prices are treated as inclusive of tax (e.g. VAT-included). |

### `TaxMode`

Pure enum representing the two pricing modes.

| Case | Description |
|---|---|
| `Inclusive` | Stored prices already include tax. Display as-is; extract tax at checkout if needed. |
| `Exclusive` | Stored prices exclude tax. Add tax at display or checkout time. |

### `TaxModeResolver`

| Method | Return type | Throws | Description |
|---|---|---|---|
| `mode()` | `TaxMode` | `ConfigNotFoundException`, `InvalidConfigValueException`, `SecretCipherException` | Read `tax/prices_include_tax` from config and return `TaxMode::Inclusive` or `TaxMode::Exclusive`. |

## Related Packages

- [markommerce/tax-market](/docs/packages/tax-market/) --- Per-market `tax/prices_include_tax` override bridge
- [markommerce/config](/docs/packages/config/) --- Config system that backs `TaxConfig`
- [markommerce/config-scope](/docs/packages/config-scope/) --- Scope-aware config resolution for per-locale, per-market overrides
