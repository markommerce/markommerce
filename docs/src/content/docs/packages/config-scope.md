---
title: markommerce/config-scope
description: Scope-aware config resolution for Markommerce — bridges markommerce/config and markommerce/scope to resolve configuration values within a given scope context.
---

Scope-aware config resolution for Markommerce. `markommerce/config-scope` bridges `markommerce/config` and `markommerce/scope` to layer per-scope overrides on top of the base config system. Install this package when you need configuration values that vary by locale, market, channel, or any other registered axis.

## Installation

```bash
composer require markommerce/config-scope
```

The package ships its PostgreSQL implementation directly — no additional driver package is required.

## Usage

### Declaring a scoped config property

Add `#[Scoped(axes: [...])]` alongside `#[Config]` on any property that should carry per-scope overrides. The axes list must match axes registered in `config/scope.php`:

```php title="app/shop/Config/ShopConfig.php"
<?php

declare(strict_types=1);

namespace App\Shop\Config;

use Markommerce\Config\Attributes\Config;
use Markommerce\Scope\Attributes\Scoped;

class ShopConfig
{
    #[Config(key: 'shop/display.welcome_message')]
    #[Scoped(axes: ['locale'])]
    public string $welcomeMessage = 'Welcome!';

    #[Config(key: 'shop/display.items_per_page')]
    #[Scoped(axes: ['locale', 'market'])]
    public int $itemsPerPage = 24;
}
```

### Reading scoped config values

Inject `ScopedConfigResolver` (the Preference that replaces `ConfigResolver`). Call `get()` as usual --- the proxy returned by `get()` resolves each property against the active `ScopeContext`:

```php
<?php

declare(strict_types=1);

use App\Shop\Config\ShopConfig;
use Markommerce\ConfigScope\ScopedConfigResolver;

class ShopController
{
    public function __construct(private ScopedConfigResolver $scopedConfigResolver) {}

    public function index(): void
    {
        $cfg = $this->scopedConfigResolver->get(ShopConfig::class);

        // Resolves to the locale:de override when $scopeContext->in('locale', 'de') is active
        echo $cfg->welcomeMessage;

        // Resolves to the most-specific locale+market override
        echo $cfg->itemsPerPage;
    }
}
```

For a specific scope regardless of the active context, use `resolvedAt()`:

```php
<?php

declare(strict_types=1);

use Markommerce\Scope\Context\ScopeContext;

$deContext = new ScopeContext($registry);
$deContext->in('locale', 'de');

$value = $scopedConfigResolver->resolvedAt(ShopConfig::class, 'welcomeMessage', $deContext);
```

### Writing scoped overrides

Inject `ScopedConfigWriterInterface` to write overrides keyed by `ScopeSignature`. The writer validates that every axis in the signature is declared on the property's `#[Scoped]` attribute:

```php
<?php

declare(strict_types=1);

use Markommerce\ConfigScope\Contracts\ScopedConfigWriterInterface;
use Markommerce\Scope\Signature\ScopeSignature;

class MerchantSettingsService
{
    public function __construct(private ScopedConfigWriterInterface $scopedConfigWriter) {}

    public function setGermanWelcome(string $message): void
    {
        $this->scopedConfigWriter->setOverride(
            'shop/display.welcome_message',
            ScopeSignature::fromArray(['locale' => 'de']),
            $message,
        );
    }

    public function resetGermanWelcome(): void
    {
        $this->scopedConfigWriter->unsetOverride(
            'shop/display.welcome_message',
            ScopeSignature::fromArray(['locale' => 'de']),
        );
    }
}
```

`setOverride()` throws `AxisNotDeclaredException` if the signature references an axis not declared on the property.

### Override resolution

When `ScopedConfigResolver` reads a property, it follows this precedence:

1. **Scoped override** --- the most specific override matching the active `ScopeContext` (using `markommerce/scope`'s walk-up hierarchy).
2. **Global value** --- a value written without a scope signature via `ConfigWriterInterface::setGlobal()`.
3. **Declared default** --- the PHP default value on the property.

Properties without `#[Scoped]` always return the global or default value and are never affected by scope context.

### CLI commands

`markommerce/config-scope` adds a `--scope` option to the base `config:get`, `config:set`, and `config:unset` commands via Preference replacements. All three commands accept the option:

| Command | Description |
|---|---|
| `config:get <key> --scope=axis=value,...` | Read a config value resolved at a specific scope. |
| `config:set <key> <value> --scope=axis=value,...` | Write a scoped override. |
| `config:unset <key> --scope=axis=value,...` | Remove a scoped override. |

```bash
# Read the German welcome message
php marko config:get shop/display.welcome_message --scope=locale=de

# Set a German override
php marko config:set shop/display.welcome_message "Willkommen!" --scope=locale=de

# Remove the German override
php marko config:unset shop/display.welcome_message --scope=locale=de
```

### Testing

`InMemoryScopedConfigStorage` is provided for use in unit tests. Wire it as the `ScopedConfigStorageInterface` binding in your test container:

```php
<?php

declare(strict_types=1);

use Markommerce\ConfigScope\Contracts\ScopedConfigStorageInterface;
use Markommerce\ConfigScope\Storage\InMemoryScopedConfigStorage;

$container->bind(ScopedConfigStorageInterface::class, InMemoryScopedConfigStorage::class);
```

## API Reference

### `ScopedConfigResolver`

Preference that replaces `ConfigResolver`. Resolves config values against the active `ScopeContext`.

| Method | Description |
|---|---|
| `get(string $configClass): object` | Return a typed proxy whose properties resolve against the active scope context. |
| `resolved(string $configClass, string $field): mixed` | Resolve a single property against the active `ScopeContext`. |
| `resolvedAt(string $configClass, string $field, ScopeContext $context): mixed` | Resolve a single property at an explicit scope context. |

### `ScopedConfigWriterInterface`

Extends `ConfigWriterInterface` with scope-override methods.

| Method | Throws | Description |
|---|---|---|
| `setOverride(string $key, ScopeSignature $signature, mixed $value): void` | `ConfigNotFoundException`, `AxisNotDeclaredException` | Write a scoped override. |
| `unsetOverride(string $key, ScopeSignature $signature): void` | `ConfigNotFoundException`, `AxisNotDeclaredException` | Remove a scoped override. |

### `ScopedConfigStorageInterface`

Implement this interface to provide a custom storage backend for scoped overrides.

| Method | Description |
|---|---|
| `loadOverrides(string $key): array<string, mixed>` | Load all overrides for a config key. Returns a map of serialized signature to raw value. |
| `loadManyOverrides(array $keys): array<string, array<string, mixed>>` | Load overrides for multiple keys at once. |
| `saveOverride(string $key, string $signature, mixed $value): void` | Save or replace a single override. |
| `deleteOverride(string $key, string $signature): void` | Remove a single override. |

### `AxisNotDeclaredException`

Thrown by `ScopedConfigWriter::setOverride()` when the `ScopeSignature` references an axis that is not declared on the target property's `#[Scoped]` attribute.

## Related Packages

- [markommerce/config](/docs/packages/config/) --- Core config package: attributes, resolver, writer, CLI, and in-memory fake
- [markommerce/scope](/docs/packages/scope/) --- Scope axes, `ScopeContext`, and `ScopeSignature`
- [markommerce/config-locale](/docs/packages/config-locale/) --- Bridge that registers the `locale` axis for config properties
- [markommerce/config-market](/docs/packages/config-market/) --- Bridge that registers the `market` axis for config properties
