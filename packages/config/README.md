# markommerce/config

Developer-declared, merchant-overridable configuration values for Markommerce stores.

> **Not `marko/config`** — this package manages _merchant-editable_ store settings (e.g. "welcome message", "items per page"). Marko's own `marko/config` handles static environment configuration (env vars, config files). The two are unrelated.

## Installation

```bash
composer require markommerce/config
```

The package ships its PostgreSQL implementation directly — no separate driver package is required.

## Quick Example

```php
use Markommerce\Config\Attributes\Config;
use Markommerce\Config\ConfigResolver;

class CatalogConfig
{
    #[Config(key: 'catalog/display.items_per_page')]
    public int $itemsPerPage = 24;
}

// Inject ConfigResolver and call get() to obtain a typed proxy
$cfg = $configResolver->get(CatalogConfig::class);
echo $cfg->itemsPerPage; // 24, or merchant-set override
```

For per-scope overrides (locale, market, channel), install `markommerce/config-scope`.

## Documentation

Full usage, API reference, and examples: [markommerce/config](https://markommerce.dev/docs/packages/config/)
