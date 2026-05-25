---
title: markommerce/config
description: Developer-declared, merchant-overridable, per-scope configuration values for Markommerce stores.
---

Developer-declared, merchant-overridable, per-scope configuration values for Markommerce stores. `markommerce/config` lets module authors define typed configuration classes whose properties carry default values and optional per-scope overrides. Merchants write values at runtime (globally or scoped to a channel, locale, or any other axis) without touching code. The package ships attributes, a resolver, a writer, a code-generation CLI, and an in-memory storage fake for testing --- but no database driver. Install `markommerce/config-pgsql` for PostgreSQL persistence.

> **Not `marko/config`** --- this package manages merchant-editable store settings (e.g. "items per page", "welcome message"). Marko's own `marko/config` handles static environment configuration (env vars, config files). The two systems are unrelated.

## Installation

```bash
composer require markommerce/config
```

A storage driver is also required. Install the PostgreSQL driver:

```bash
composer require markommerce/config-pgsql
```

## Usage

### Declaring a config class

Create a plain PHP class. Annotate each property with `#[Config]`, giving it a dot-separated key in `vendor/group.setting` format. Properties that should resolve differently per scope also get `#[Scoped]` from `markommerce/scope`:

```php title="app/catalog/Config/CatalogConfig.php"
<?php

declare(strict_types=1);

namespace App\Catalog\Config;

use Markommerce\Config\Attributes\Config;
use Markommerce\Scope\Attributes\Scoped;

class CatalogConfig
{
    #[Config(key: 'catalog/display.items_per_page')]
    #[Scoped(axes: ['channel'])]
    public int $itemsPerPage = 24;

    #[Config(key: 'catalog/display.welcome_message')]
    public string $welcomeMessage = 'Welcome!';

    #[Config(key: 'catalog/api.secret_token', secret: true)]
    public string $apiSecretToken = '';
}
```

Config classes are discovered automatically at boot --- no manual registration is needed. The framework scans each module's `src/` directory for classes with `#[Config]` properties.

**Constraints on config classes:**

- All constructor parameters must be optional (the proxy generator instantiates the class without arguments).
- Properties must not be `readonly`.
- Property types must be simple scalars, nullable scalars, class types, or backed enums --- union and intersection types are not supported.
- Non-nullable properties must have a default value.

### Generating proxy classes

After declaring or modifying a config class, run the code generator to produce the typed proxy:

```bash
php marko config:generate
```

This writes PHP 8.4 property-hook subclasses to `var/generated/config/`. Each config class gets a `{ClassName}_Resolved` sibling in the `Markommerce\Config\Generated\` namespace hierarchy. Generated proxies are loaded automatically at boot via `ProxyAutoloader`.

Add `config:generate` to your `composer install` scripts and CI pipeline so proxies are always up to date:

```json title="composer.json"
{
    "scripts": {
        "post-install-cmd": ["php marko config:generate"],
        "post-update-cmd": ["php marko config:generate"]
    }
}
```

**Dev-mode auto-regeneration:** Set `markommerce.config.auto_regenerate = true` in Marko's config to regenerate stale proxies automatically on every boot. This is convenient during development but should be disabled in production.

### Reading config values

Inject `ConfigResolver` and call `get()` with the config class name. The resolver returns a typed proxy instance (`CatalogConfig_Resolved`) whose properties call back into the resolver on each access, observing the active `ScopeContext`:

```php
<?php

declare(strict_types=1);

use App\Catalog\Config\CatalogConfig;
use Markommerce\Config\ConfigResolver;

class CatalogController
{
    public function __construct(private ConfigResolver $configResolver) {}

    public function index(): void
    {
        $cfg = $this->configResolver->get(CatalogConfig::class);

        echo $cfg->itemsPerPage;   // 24, or merchant-set override for the active channel
        echo $cfg->welcomeMessage; // 'Welcome!', or merchant-set value
    }
}
```

The proxy is a real PHP object typed as `CatalogConfig`, so static analysis tools and IDE autocompletion work without any special plugins.

**Per-request caching:** The module wires `CachingConfigResolver` as the concrete implementation behind `ConfigResolver`. Resolved values are cached for the lifetime of the HTTP request (keyed by config key plus active scope axes). `ConfigCacheResetMiddleware` clears the cache at the start of each request, registered as global middleware at priority 10.

### Writing config values

Inject `ConfigWriterInterface` to write global values or per-scope overrides. The writer validates the key against the registry and applies an optimistic-locking retry loop (up to 3 attempts). `StaleConfigWriteException` is thrown when all retries are exhausted due to concurrent writes.

```php
<?php

declare(strict_types=1);

use Markommerce\Config\Contracts\ConfigWriterInterface;
use Markommerce\Scope\Signature\ScopeSignature;

class MerchantSettingsService
{
    public function __construct(private ConfigWriterInterface $configWriter) {}

    public function setGlobalItemsPerPage(int $value): void
    {
        $this->configWriter->setGlobal('catalog/display.items_per_page', $value);
    }

    public function setMobileItemsPerPage(int $value): void
    {
        $this->configWriter->setOverride(
            'catalog/display.items_per_page',
            new ScopeSignature(['channel' => 'mobile']),
            $value,
        );
    }

    public function resetMobileItemsPerPage(): void
    {
        $this->configWriter->unsetOverride(
            'catalog/display.items_per_page',
            new ScopeSignature(['channel' => 'mobile']),
        );
    }
}
```

`ConfigWriterInterface` exposes four methods:

| Method | Description |
|---|---|
| `setGlobal(string $key, mixed $value): void` | Write a value that applies when no scope override matches. |
| `unsetGlobal(string $key): void` | Remove the global value, reverting to the declared default. |
| `setOverride(string $key, ScopeSignature $signature, mixed $value): void` | Write a scoped override. Throws `AxisNotDeclaredException` if the signature references an axis not declared on the property's `#[Scoped]` attribute. |
| `unsetOverride(string $key, ScopeSignature $signature): void` | Remove a scoped override. |

### Secret values

Mark a property `secret: true` to encrypt its value at rest. The cipher uses libsodium (`sodium_crypto_secretbox`). The key must be provided via the `MARKOMMERCE_CONFIG_SECRET_KEY` environment variable as a 32-byte raw binary string.

```php
#[Config(key: 'payment/stripe.secret_key', secret: true)]
public string $stripeSecretKey = '';
```

Secrets are encrypted before storage and decrypted on read. The `config:get` CLI command always prints `***` for secret keys regardless of the current value.

**Setting the secret key:** Generate a 32-byte key and set it in your environment:

```bash
php -r "echo base64_encode(random_bytes(32));" > .secret_key
export MARKOMMERCE_CONFIG_SECRET_KEY=$(cat .secret_key | base64 --decode)
```

The module throws `SecretCipherException::notConfigured()` at boot if the env var is missing or empty and at least one secret property is declared.

### Override resolution

When `ConfigResolver` reads a value, it follows this precedence:

1. **Scoped override** --- the most specific override matching the active `ScopeContext` (using `markommerce/scope`'s walk-up hierarchy).
2. **Global value** --- a value written without a scope signature.
3. **Declared default** --- the PHP default value on the property.

Only axes declared in `#[Scoped]` on the property participate in resolution. A property without `#[Scoped]` never considers scope overrides, even if the active context has axes set.

### Customizing config classes with Preferences

To override a config class from a downstream module, create a subclass and annotate it with Marko's `#[Preference]` attribute:

```php
<?php

declare(strict_types=1);

namespace App\MyModule\Config;

use App\Catalog\Config\CatalogConfig;
use Marko\Core\Attributes\Preference;
use Markommerce\Config\Attributes\Config;

#[Preference(replaces: CatalogConfig::class)]
class ExtendedCatalogConfig extends CatalogConfig
{
    #[Config(key: 'catalog/display.featured_count')]
    public int $featuredCount = 6;
}
```

Run `config:generate` after adding a Preference. The generator produces proxy classes for both the original class and the preference subclass. `ConfigResolver::get(CatalogConfig::class)` automatically resolves to the preferred class via Marko's `PreferenceRegistry`.

## CLI Commands

| Command | Description |
|---|---|
| `config:list` | List all registered config keys, their source class, declared axes, and whether each is a secret. |
| `config:get <key>` | Print the resolved value for a key (prints `***` for secrets). Accepts `--scope=axis=value,axis2=value2` to resolve at a specific scope. |
| `config:set <key> <value>` | Write a global value. Accepts `--scope=axis=value,axis2=value2` to write a scoped override instead. |
| `config:unset <key>` | Remove a global value. Accepts `--scope=axis=value,axis2=value2` to remove a scoped override instead. |
| `config:generate` | Scan all registered config classes and write typed proxy files to `var/generated/config/`. |

```bash
# List all registered keys
php marko config:list

# List in JSON format
php marko config:list --format=json

# Read a single key (current scope context)
php marko config:get catalog/display.items_per_page

# Read at a specific scope
php marko config:get catalog/display.items_per_page --scope=channel=mobile

# Set a global value
php marko config:set catalog/display.items_per_page 12

# Set a scoped override
php marko config:set catalog/display.items_per_page 8 --scope=channel=mobile

# Remove a scoped override
php marko config:unset catalog/display.items_per_page --scope=channel=mobile

# Generate proxy classes after changing a config class
php marko config:generate
```

## Testing

`InMemoryConfigStorage` is provided for use in tests. Wire it as the storage implementation in your test bootstrap so no database is needed:

```php
<?php

declare(strict_types=1);

use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\Contracts\ConfigStorageInterface;

// In your test container setup
$container->bind(ConfigStorageInterface::class, InMemoryConfigStorage::class);
```

`InMemoryConfigStorage` implements the same optimistic-locking semantics as the PostgreSQL driver: `compareAndSave()` checks the expected version and returns `false` on a version mismatch. Tests that exercise concurrent-write scenarios can rely on this behavior without a real database.

## API Reference

### `#[Config]`

Property attribute that declares a config key.

| Parameter | Type | Default | Description |
|---|---|---|---|
| `key` | `string` | required | Dot-separated config key in `vendor/group.setting` format. Must not be empty. |
| `secret` | `bool` | `false` | When `true`, values are encrypted at rest using libsodium and never printed in plain text by CLI commands. |

### `ConfigResolver`

Resolves a typed config proxy. The module binds this as a singleton wrapping `CachingConfigResolver`.

| Method | Returns | Description |
|---|---|---|
| `get(string $configClass): object` | `T` (the config class type) | Return a typed proxy instance. Throws `ProxyNotGeneratedException` if `config:generate` has not been run. Throws `InvalidConfigClassException` if the registered Preference is not a subclass of the requested class. |

### `ConfigWriterInterface`

| Method | Throws | Description |
|---|---|---|
| `setGlobal(string $key, mixed $value): void` | `ConfigNotFoundException`, `StaleConfigWriteException` | Write a global (non-scoped) value. |
| `unsetGlobal(string $key): void` | `ConfigNotFoundException`, `StaleConfigWriteException` | Remove the global value. |
| `setOverride(string $key, ScopeSignature $signature, mixed $value): void` | `ConfigNotFoundException`, `AxisNotDeclaredException`, `StaleConfigWriteException` | Write a scoped override. |
| `unsetOverride(string $key, ScopeSignature $signature): void` | `ConfigNotFoundException`, `AxisNotDeclaredException`, `StaleConfigWriteException` | Remove a scoped override. |

### `ConfigStorageInterface`

Implement this interface to provide a custom storage backend.

| Method | Description |
|---|---|
| `load(string $key): ?ConfigRow` | Load a single row by key. Returns `null` if the key has never been written. |
| `loadMany(array $keys): array<string, ConfigRow>` | Load multiple rows by key. Returns only the keys that exist. |
| `compareAndSave(string $key, ConfigRow $row, int $expectedVersion): bool` | Atomic compare-and-swap. Returns `true` on success, `false` on version mismatch. |

### `SecretCipherInterface`

| Method | Description |
|---|---|
| `encrypt(string $plaintext): string` | Encrypt a plaintext value. Returns a base64-encoded ciphertext. |
| `decrypt(string $ciphertext): string` | Decrypt a ciphertext value. Throws `SecretCipherException` if the ciphertext is invalid or has been tampered with. |

### `ConfigRegistry`

Read-only registry built at boot from all discovered config classes.

| Method | Description |
|---|---|
| `definition(string $configClass, string $field): ConfigDefinition` | Look up a definition by class and field name. Throws `ConfigNotFoundException`. |
| `byKey(string $key): ConfigDefinition` | Look up a definition by config key string. Throws `ConfigNotFoundException`. |
| `all(): list<ConfigDefinition>` | Return all registered definitions. |

### `ConfigDefinition`

Value object describing a single config property.

| Property | Type | Description |
|---|---|---|
| `$key` | `string` | The config key string (e.g. `catalog/display.items_per_page`). |
| `$configClass` | `class-string` | The config class that declares this property. |
| `$field` | `string` | The PHP property name. |
| `$axes` | `list<string>` | Scope axes declared on the property's `#[Scoped]` attribute (empty if not scoped). |
| `$type` | `string` | The PHP type name of the property (e.g. `int`, `string`, `bool`). |
| `$defaultValue` | `mixed` | The declared PHP default value. |
| `$secret` | `bool` | Whether the value is stored encrypted. |

### `ConfigRow`

Value object representing a persisted config row.

| Property | Type | Description |
|---|---|---|
| `$key` | `string` | The config key. |
| `$value` | `mixed` | The global value (raw JSONB-decoded), or `null` if not set. |
| `$overrides` | `array<string, mixed>` | Map of serialized scope signature to raw value. |
| `$version` | `int` | Optimistic-lock version counter. Starts at 0 for rows that have never been saved. |
| `$updatedAt` | `?DateTimeImmutable` | Timestamp of the last write, or `null` for unsaved rows. |

### Exceptions

| Exception | When thrown |
|---|---|
| `ConfigNotFoundException` | A config key or class+field combination is not in the registry. |
| `ProxyNotGeneratedException` | `ConfigResolver::get()` was called but the proxy class for the requested config class does not exist. Run `config:generate`. |
| `StaleConfigWriteException` | All 3 optimistic-lock retry attempts failed due to concurrent writes. |
| `AxisNotDeclaredException` | `setOverride()` or `unsetOverride()` was called with a scope signature axis that is not declared on the target property's `#[Scoped]` attribute. |
| `InvalidConfigClassException` | A config class violates the declared constraints (required constructor, `readonly` property, union type, unsupported Preference). |
| `SecretCipherException` | The `MARKOMMERCE_CONFIG_SECRET_KEY` env var is missing, the key is the wrong length, the sodium extension is unavailable, or a ciphertext has been tampered with. |
| `InvalidConfigValueException` | A stored value cannot be cast to the declared property type. |
| `ConfigKeyConflictException` | Two config classes declare the same key string. |

## Related Packages

- [markommerce/config-pgsql](/docs/packages/config-pgsql/) --- PostgreSQL storage driver
- [markommerce/scope](/docs/packages/scope/) --- Scope axes and `ScopeContext` used for per-scope overrides
