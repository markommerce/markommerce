# markommerce/config-locale

Locale-aware config resolution for Markommerce --- bridges `markommerce/config-scope` and `markommerce/locale` to resolve configuration values per locale.

## Installation

```bash
composer require markommerce/config-locale
```

## Quick Example

Once this package is fully implemented, it will register the `locale` axis for config properties automatically. Merchants will be able to override any `#[Scoped(axes: ['locale'])]` config property per locale without extra wiring:

```php
use Markommerce\Config\Attributes\Config;
use Markommerce\Scope\Attributes\Scoped;

class ShopConfig
{
    #[Config(key: 'shop/display.welcome_message')]
    #[Scoped(axes: ['locale'])]
    public string $welcomeMessage = 'Welcome!';
}
```

## Placeholder Status

This package is a **placeholder bridge**. The `boot` closure currently registers no scoped config fields --- locale-scoped config resolution is not yet implemented.
See [FEATURES.md](../../FEATURES.md) tier rows for the planned end state.

## Documentation

Full usage, API reference, and examples: [markommerce/config-locale](https://markommerce.dev/docs/packages/config-locale/)
