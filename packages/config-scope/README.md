# markommerce/config-scope

Scope-aware config resolution for Markommerce --- bridges `markommerce/config` and `markommerce/scope` to resolve configuration values within a given scope context.

## Installation

```bash
composer require markommerce/config-scope
```

A PostgreSQL driver is also required:

```bash
composer require markommerce/config-scope-pgsql
```

## Quick Example

Mark a config property as scoped with `#[Scoped(axes: ['locale'])]`, then `ScopedConfigResolver` returns the most-specific override for the active `ScopeContext`:

```php
use Markommerce\Config\Attributes\Config;
use Markommerce\Scope\Attributes\Scoped;

class ShopConfig
{
    #[Config(key: 'shop/display.welcome_message')]
    #[Scoped(axes: ['locale'])]
    public string $welcomeMessage = 'Welcome!';
}

// Inject ScopedConfigResolver and call get() — returns a typed proxy
$cfg = $scopedConfigResolver->get(ShopConfig::class);
echo $cfg->welcomeMessage; // locale-specific value, or 'Welcome!' if no override
```

## Documentation

Full usage, API reference, and examples: [markommerce/config-scope](https://markommerce.dev/docs/packages/config-scope/)
