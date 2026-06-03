# markommerce/currency

Globally configurable base currency for Markommerce — exposes a `currency/base` config key and a `CurrencyResolver` that maps the configured ISO 4217 code into a `Currency` value object via the currency registry.

## Installation

```bash
composer require markommerce/currency
```

## Quick Example

The package ships a `CurrencyConfig` class with a `currency/base` key defaulting to `USD`:

```php
// Default: currency/base = 'USD'
// Override via config storage, e.g. set currency/base = 'EUR'

use Markommerce\Currency\CurrencyResolver;

// $currencyResolver is a CurrencyResolver injected by the container
$currency = $currencyResolver->base();
// => Currency { code: 'EUR', scale: 2, symbol: '€', name: 'Euro' }
```

`CurrencyResolver::base()` reads the configured code through `ConfigResolver::resolved()` and returns the matching `Currency` value object from the registry. When `markommerce/config-scope` is loaded, the resolver automatically honors the active scope — no code change required.

## Documentation

Full usage, API reference, and examples: [markommerce/currency](https://markommerce.dev/docs/packages/currency/)
