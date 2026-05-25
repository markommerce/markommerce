# markommerce/config

Developer-declared, merchant-overridable, per-scope configuration values for Markommerce stores.

> **Not `marko/config`** — this package manages _merchant-editable_ store settings (e.g. "welcome message", "items per page"). Marko's own `marko/config` handles static environment configuration (env vars, config files). The two are unrelated.

## Installation

```bash
composer require markommerce/config
```

A storage driver is also required:

```bash
composer require markommerce/config-pgsql
```

## Quick Example

```php
use Markommerce\Config\Attributes\Config;
use Markommerce\Config\ConfigResolver;
use Markommerce\Scope\Attributes\Scoped;

class CatalogConfig
{
    #[Config(key: 'catalog/display.items_per_page')]
    #[Scoped(axes: ['channel'])]
    public int $itemsPerPage = 24;
}

// Inject ConfigResolver and call get() to obtain a typed proxy
$cfg = $configResolver->get(CatalogConfig::class);
echo $cfg->itemsPerPage; // 24, or merchant-set override for the active channel
```

## Documentation

Full usage, API reference, and examples: [markommerce/config](https://markommerce.dev/docs/packages/config/)
