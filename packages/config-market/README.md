# markommerce/config-market

Market-aware config resolution for Markommerce — bridges `markommerce/config-scope` and `markommerce/market` to resolve configuration values per market.

## Installation

```bash
composer require markommerce/config-market
```

## Quick Example

Once this package is fully implemented, it will register the `market` axis for config properties automatically. Merchants will be able to override any `#[Scoped(axes: ['market'])]` config property per market without extra wiring:

```php
use Markommerce\Config\Attributes\Config;
use Markommerce\Scope\Attributes\Scoped;

class PricingConfig
{
    #[Config(key: 'pricing/display.currency')]
    #[Scoped(axes: ['market'])]
    public string $currency = 'USD';
}
```

## Placeholder Status

This package is a **placeholder bridge**. The `boot` closure currently registers no scoped config fields — market-scoped config resolution is not yet implemented.
See [FEATURES.md](../../FEATURES.md) tier rows for the planned end state.

## Documentation

Full usage, API reference, and examples: [markommerce/config-market](https://markommerce.dev/docs/packages/config-market/)
