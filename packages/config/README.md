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

Declare a typed config class:

```php
use Markommerce\Config\Attributes\Config;
use Markommerce\Scope\Attributes\Scoped;

class CatalogConfig
{
    #[Config(key: 'catalog/display.items_per_page')]
    #[Scoped(axes: ['channel'])]
    public int $itemsPerPage = 24;

    #[Config(key: 'catalog/display.welcome_message', secret: false)]
    public string $welcomeMessage = 'Welcome!';
}
```

Read it via the injected resolver — reads observe the active `ScopeContext`:

```php
use Markommerce\Config\ConfigResolver;

class CatalogController
{
    public function __construct(private ConfigResolver $config) {}

    public function index(): void
    {
        $cfg = $this->config->get(CatalogConfig::class);
        echo $cfg->itemsPerPage;       // 24, or merchant-set override
        echo $cfg->welcomeMessage;     // 'Welcome!', or merchant-set value
    }
}
```

Write a merchant override scoped to a channel:

```php
use Markommerce\Config\Contracts\ConfigWriterInterface;
use Markommerce\Scope\Signature\ScopeSignature;

$writer->set('catalog/display.items_per_page', 12, ScopeSignature::fromArray(['channel' => 'mobile']));
```

## CLI Commands

```bash
# List all registered config keys and their current values
php marko config:list

# Read a single key
php marko config:get catalog/display.items_per_page

# Set a global value
php marko config:set catalog/display.items_per_page 12

# Set a scoped override (channel=mobile)
php marko config:set catalog/display.items_per_page 12 --scope channel:mobile

# Remove an override
php marko config:unset catalog/display.items_per_page --scope channel:mobile

# Generate typed proxy classes (run after adding or changing config classes)
php marko config:generate
```

Add `config:generate` to your `composer install` scripts and CI pipeline so proxies are always up to date.

## Documentation

Full usage, API reference, and examples: [markommerce/config](/docs/packages/config/)
