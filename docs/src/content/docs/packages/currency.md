---
title: markommerce/currency
description: Globally configurable base currency for Markommerce — a currency/base config key and a CurrencyResolver that maps the configured ISO 4217 code to a Currency value object.
---

Globally configurable base currency for Markommerce. `markommerce/currency` ships a `CurrencyConfig` class with a `currency/base` config key (default `'USD'`) and a `CurrencyResolver` that reads the configured code through `ConfigResolver` and returns the matching `Currency` value object from the registry. The source of truth for the active currency is always config --- a product stores only the price amount; the currency is resolved separately.

## Installation

```bash
composer require markommerce/currency
```

## Configuration

The base currency is controlled by the `currency/base` config key, declared in `CurrencyConfig`:

```php title="packages/currency/src/Config/CurrencyConfig.php"
use Markommerce\Config\Attributes\Config;

class CurrencyConfig
{
    #[Config(key: 'currency/base')]
    public string $base = 'USD';
}
```

The default is `'USD'`. Override it globally via `ConfigWriterInterface::setGlobal('currency/base', 'EUR')` or, when `markommerce/config-scope` is installed, via a scoped override. After changing the class, regenerate the config proxy:

```bash
php marko config:generate
```

## Usage

### Resolving the base currency

Inject `CurrencyResolver` and call `base()`. The resolver reads the configured code and looks it up in `CurrencyRegistryInterface`:

```php
use Markommerce\Currency\CurrencyResolver;

// $currencyResolver is injected by the container
$currency = $currencyResolver->base();
// => Currency { code: 'EUR', scale: 2, symbol: '€', name: 'Euro' }

echo $currency->code;    // 'EUR'
echo $currency->scale;   // 2
echo $currency->symbol;  // '€'
```

When `markommerce/config-scope` is active, `ConfigResolver` is replaced by `ScopedConfigResolver` via a Preference, so `base()` automatically honors the active scope without any code change.

### Per-market currency overrides

Install [markommerce/currency-market](/docs/packages/currency-market/) to make `currency/base` overridable per market. That bridge registers `CurrencyConfig::$base` on the `market` axis via `ScopedFieldRegistry`.

## API Reference

### `CurrencyConfig`

Config class declaring the base currency key.

| Property | Config key | Default | Description |
|---|---|---|---|
| `$base` | `currency/base` | `'USD'` | ISO 4217 code of the store's base currency. |

### `CurrencyResolver`

| Method | Return type | Throws | Description |
|---|---|---|---|
| `base()` | `Currency` | `ConfigNotFoundException`, `InvalidConfigValueException`, `SecretCipherException`, `UnknownCurrencyException` | Read the configured `currency/base` code and return the matching `Currency` from the registry. |

`UnknownCurrencyException` is thrown when the configured code is not registered in `CurrencyRegistryInterface`. See [markommerce/money](/docs/packages/money/) for the built-in registry and how to extend it.

## Related Packages

- [markommerce/money](/docs/packages/money/) --- `Currency` value object and `CurrencyRegistryInterface`
- [markommerce/config](/docs/packages/config/) --- Config system that backs `CurrencyConfig`
- [markommerce/currency-market](/docs/packages/currency-market/) --- Per-market `currency/base` override bridge
- [markommerce/pricing](/docs/packages/pricing/) --- Uses `CurrencyResolver` to pair the resolved amount with the active currency
