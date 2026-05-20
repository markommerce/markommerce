---
title: markommerce/scope
description: Scoped entity attributes with multi-axis hierarchical fallback.
---

Scoped attributes for entities with multi-axis hierarchical fallback. `markommerce/scope` defines the contracts and core logic for attaching per-scope override values to entity properties. Each property marked `#[Scoped]` can carry different values across multiple independent axes (e.g. `locale`, `market`, `channel`), with automatic walk-up through the declared hierarchy when no exact match exists. The package ships the `#[Scoped]` attribute, `ScopeContext`, `ScopeResolver`, `ScopeSignature`, `HasScopesInterface`, the `HasScopes` trait, and `ScopedOrderBy` query specification --- but no database driver. Applications must install `markommerce/scope-pgsql` to persist and query overrides. Scope storage is provided by the `HasScopes` trait: entities implement `HasScopesInterface` and include the trait, which declares a `$scopes` JSON column automatically.

## Installation

```bash
composer require markommerce/scope
```

No field renderer is bound by default. You must also install a driver package:

```bash
composer require markommerce/scope-pgsql
```

## Configuration

Declare axes in `config/scope.php`. Each axis requires a `default` key naming the root/global scope (where the base entity property value lives) and a `scopes` map listing every valid scope path:

```php title="config/scope.php"
<?php

declare(strict_types=1);

return [
    'axes' => [
        'locale' => [
            'default' => 'default',
            'scopes'  => [
                'default' => [],
                'en'      => [],
                'de'      => [],
                'de-DE'   => [],
                'de-AT'   => [],
                'fr'      => [],
                'fr-FR'   => [],
                'fr-BE'   => [],
            ],
        ],
        'channel' => [
            'default' => 'web',
            'scopes'  => [
                'web' => [],
                'b2b' => [],
                'b2c' => [],
            ],
        ],
        'market' => [
            'default' => 'default',
            'scopes'  => [
                'default'  => [],
                'eu'       => [],
                'eu.de'    => [],
                'eu.fr'    => [],
                'eu.at'    => [],
                'us'       => [],
                'us.east'  => [],
                'us.west'  => [],
            ],
        ],
    ],
];
```

The package ships a minimal `config/scope.php` with `locale`, `market`, and `channel` axes as a starting point. Extend it with the scope paths your application needs.

Paths use dot notation. `walkUp('eu.de')` yields `['eu.de', 'eu']`, so a value set at `eu` is inherited by `eu.de` when no `eu.de`-specific override exists.

**The default scope is the axis's base value.** The scope named by the `default` key represents the entity's base property value --- storing an override there is an error. `setOverride()` and `clearOverride()` both throw `ScopeStorageException` when the signature references an axis at its default scope. Signatures containing a default-scope path also fail `ScopeSignatureValidator` validation. This enforces the invariant that the base column is the single source of truth for the default-scope value.

**Configuration is validated at boot.** `PhpScopeRegistry` throws `ScopeConfigurationException` if any axis is missing the `default` key, declares an empty `scopes` map, or names a `default` path that is not in the `scopes` map.

## Usage

### Marking a property as scoped

Add `#[Scoped(axes: [...])]` to any entity property that should carry per-scope override values. Implement `HasScopesInterface` and use the `HasScopes` trait on your entity. The trait declares a `$scopes` JSON column automatically --- no separate migration helper is required:

```php title="app/catalog/Entity/Product.php"
<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Storage\HasScopes;
use Markommerce\Scope\Storage\HasScopesInterface;

#[Table('products')]
class Product extends Entity implements HasScopesInterface
{
    use HasScopes;

    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(length: 255)]
    #[Scoped(axes: ['locale'])]
    public string $name = '';

    #[Column(type: 'decimal', precision: 10, scale: 2)]
    #[Scoped(axes: ['channel', 'locale'])]
    public float $price = 0.0;
}
```

Register `Product` with the `SchemaRegistry`. The `scopes` column will appear in the `products` table after the next migration run.

### Setting the active context

Inject `ScopeContext` and call `in()` to set the active path for each axis before resolving values:

```php
<?php

declare(strict_types=1);

use Markommerce\Scope\Context\ScopeContext;

$context->in('locale', 'de-DE');
$context->in('channel', 'b2b');
```

### Single-axis usage example

A single-axis property resolves by walking up the declared locale hierarchy:

```php
<?php

declare(strict_types=1);

use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignature;

$product = new Product();
$product->name = 'Widget';

// Set a German locale override
$scopeResolver->setOverride($product, 'name', 'Widget DE', ScopeSignature::fromArray(['locale' => 'de']));

$productRepository->save($product);

// Resolve with hierarchy fallback: de-DE walks up to de
$context->in('locale', 'de-DE');
$localizedName = $scopeResolver->resolved($product, 'name'); // 'Widget DE'
```

### Two-axis composite usage example

A property scoped to two axes supports single-axis overrides and composite overrides. The resolution order is: composite (both axes) > single-axis (highest-scored axis first):

```php
<?php

declare(strict_types=1);

use App\Catalog\Entity\Product;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignature;

$product = new Product();
$product->price = 100.00;

// Two-axis composite override (highest priority)
$scopeResolver->setOverride(
    $product,
    'price',
    75.00,
    ScopeSignature::fromArray(['channel' => 'b2b', 'locale' => 'es']),
);

// Single-axis B2B channel override
$scopeResolver->setOverride($product, 'price', 85.00, ScopeSignature::fromArray(['channel' => 'b2b']));

// Single-axis Spanish locale override
$scopeResolver->setOverride($product, 'price', 90.00, ScopeSignature::fromArray(['locale' => 'es']));

$productRepository->save($product);

// Resolution priority: composite > channel:b2b > locale:es
$context->in('channel', 'b2b');
$context->in('locale', 'es');
$price = $scopeResolver->resolved($product, 'price'); // 75.00 — composite match

// Only B2B channel active — no composite match
$context->clearAll();
$context->in('channel', 'b2b');
$price = $scopeResolver->resolved($product, 'price'); // 85.00 — channel:b2b match

// Only Spanish locale active — no composite match
$context->clearAll();
$context->in('locale', 'es');
$price = $scopeResolver->resolved($product, 'price'); // 90.00 — locale:es match
```

### Three-axis composite usage example

Properties can be scoped to three or more axes. The composite override (all three axes) wins over any two-axis or one-axis partial override:

```php title="app/catalog/Entity/Product.php"
<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Storage\HasScopes;
use Markommerce\Scope\Storage\HasScopesInterface;

#[Table('products')]
class Product extends Entity implements HasScopesInterface
{
    use HasScopes;

    #[Column(type: 'decimal', precision: 10, scale: 2)]
    #[Scoped(axes: ['channel', 'locale', 'market'])]
    public float $price = 0.0;
}
```

```php
<?php

declare(strict_types=1);

use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignature;

// Full three-axis composite override (highest priority)
$scopeResolver->setOverride(
    $product,
    'price',
    65.00,
    ScopeSignature::fromArray(['channel' => 'b2b', 'locale' => 'de', 'market' => 'eu.de']),
);

// Two-axis composite: channel + market (lower priority than full composite)
$scopeResolver->setOverride(
    $product,
    'price',
    70.00,
    ScopeSignature::fromArray(['channel' => 'b2b', 'market' => 'eu.de']),
);

$productRepository->save($product);

// All three axes active — full composite wins
$context->in('channel', 'b2b');
$context->in('locale', 'de');
$context->in('market', 'eu.de');
$price = $scopeResolver->resolved($product, 'price'); // 65.00
```

### Writing overrides

Use `ScopeResolver::setOverride()` to attach a scoped value to an entity before persisting. The signature must not reference any axis at its configured default scope --- doing so throws `ScopeStorageException`. To change the default-scope value, set the entity's base property directly:

```php
<?php

declare(strict_types=1);

use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignature;

$product = new Product();
$product->name = 'Widget';       // base (default-scope) value — set directly
$product->price = 100.00;        // base (default-scope) value — set directly

// Set a German locale override for the name
$scopeResolver->setOverride($product, 'name', 'Widget DE', ScopeSignature::fromArray(['locale' => 'de']));

// Set market overrides for price at different hierarchy levels
$scopeResolver->setOverride($product, 'price', 89.99, ScopeSignature::fromArray(['market' => 'eu']));
$scopeResolver->setOverride($product, 'price', 79.99, ScopeSignature::fromArray(['market' => 'eu.de']));

$productRepository->save($product);
```

### Reading resolved values

`$product->name` returns the raw column value. Use `ScopeResolver::resolved()` to walk the active context hierarchy and return the most specific override:

```php
<?php

declare(strict_types=1);

use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Resolver\ScopeResolver;

// Set the active context
$context->in('locale', 'de-DE');
$context->in('market', 'eu.fr');

// Raw column value — no scope resolution
$raw = $product->name; // 'Widget'

// resolved() walks de-DE → de → column value
$localizedName = $scopeResolver->resolved($product, 'name'); // 'Widget DE' (de override)

// eu.fr has no override; walks up to eu
$marketPrice = $scopeResolver->resolved($product, 'price'); // 89.99 (eu override)
```

### Resolving at a specific scope

Use `resolvedAt()` to resolve a value at a particular scope regardless of the active context:

```php
<?php

declare(strict_types=1);

use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignature;

$dePrice = $scopeResolver->resolvedAt($product, 'price', ScopeSignature::fromArray(['market' => 'eu.de'])); // 79.99
```

### Explicit single-axis lookup with `walkAt`

`ScopeWalker::walkAt()` performs an explicit single-axis lookup, ignoring any ambient `ScopeContext`. It is useful when you need to resolve a value at a specific axis path without affecting the active context:

```php
<?php

declare(strict_types=1);

use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Signature\ScopeSignature;

// Single-axis lookup — resolves at locale:de, walking de-formal → de
$result = $scopeWalker->walkAt(
    $product,
    'name',
    ['locale'],
    ScopeSignature::fromArray(['locale' => 'de-formal']),
    $registry,
);
```

> **Note**: `walkAt` only accepts a single-axis `ScopeSignature`. Passing a multi-axis signature throws `MultiAxisWalkAtNotSupportedException`. For multi-axis resolution use `walk()` with a `ScopeContext`.

### Ordered queries

Use `ScopedOrderByFactory::create()` to build a `ScopedOrderBy` `QuerySpecification` that sorts by the resolved value for the active context:

```php
<?php

declare(strict_types=1);

use App\Catalog\Entity\Product;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Query\ScopedOrderByFactory;

$context->in('locale', 'de-DE');

$products = $productRepository->matching(
    $scopedOrderByFactory->create(Product::class, 'name', 'asc'),
);
```

The driver package emits a `COALESCE` expression that mirrors the PHP resolution order:

```sql
ORDER BY COALESCE(
    "scopes"->'locale:de-DE'->>'name',
    "scopes"->'locale:de'->>'name',
    "name"
) ASC
```

When no scope path is active the specification falls back to a plain `ORDER BY name ASC`.

### Clearing overrides

```php
<?php

declare(strict_types=1);

use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignature;

$scopeResolver->clearOverride($product, 'price', ScopeSignature::fromArray(['market' => 'eu.de']));
$productRepository->save($product);
```

## Customization

### DB-driven scope registry

By default, axes are loaded from `config/scope.php` via `PhpScopeRegistry`. To drive axes from a database table so they can be managed at runtime, implement `ScopeRegistryInterface` and bind it in your module:

```php title="app/catalog/Registry/DatabaseScopeRegistry.php"
<?php

declare(strict_types=1);

namespace App\Catalog\Registry;

use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

class DatabaseScopeRegistry implements ScopeRegistryInterface
{
    public function hasAxis(string $name): bool { /* ... */ }
    public function getAxis(string $name): ScopeAxis { /* ... */ }
    public function listAxes(): array { /* ... */ }
    public function getHierarchy(string $axisName): ScopeHierarchy { /* ... */ }
}
```

```php title="app/catalog/module.php"
<?php

declare(strict_types=1);

use App\Catalog\Registry\DatabaseScopeRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

return [
    'bindings' => [
        ScopeRegistryInterface::class => DatabaseScopeRegistry::class,
    ],
];
```

## API Reference

| Class / Interface | Description |
|---|---|
| `Markommerce\Scope\Attributes\Scoped` | Property attribute declaring which axes scope a value |
| `Markommerce\Scope\Context\ScopeContext` | Mutable singleton holding the active path per axis for the current request |
| `Markommerce\Scope\Resolver\ScopeResolver` | Resolves scoped values by walking the active context hierarchy; also writes and clears overrides |
| `Markommerce\Scope\Signature\ScopeSignature` | Value object representing one or more axis+path pairs; use `ScopeSignature::fromArray(['axis' => 'path'])` or `new ScopeSignature(['axis' => 'path'])` |
| `Markommerce\Scope\Signature\SignatureCandidateEnumerator` | Enumerates all candidate signatures for multi-axis resolution in descending-score order |
| `Markommerce\Scope\Signature\ScopeSignatureValidator` | Validates a `ScopeSignature` against the axes declared on a `#[Scoped]` attribute |
| `Markommerce\Scope\Storage\HasScopesInterface` | Interface for entities that store scoped overrides; `setOverride()` and `clearOverride()` throw `ScopeStorageException` when given a default-scope signature |
| `Markommerce\Scope\Storage\HasScopes` | Trait that adds a `$scopes` JSON column and the override storage methods. The consuming class must also declare `implements HasScopesInterface` --- PHP does not allow traits to enforce interface implementation. |
| `Markommerce\Scope\Storage\DefaultScopeGuard` | Static guard configured at boot; polices `HasScopes::setOverride()` and `clearOverride()` by throwing `ScopeStorageException` when a signature targets an axis at its configured default scope |
| `Markommerce\Scope\Query\ScopedOrderBy` | `QuerySpecification` that orders by resolved scope value |
| `Markommerce\Scope\Query\ScopedOrderByFactory` | Factory for building `ScopedOrderBy` specifications |
| `Markommerce\Scope\Query\ScopedFieldRendererInterface` | Interface implemented by driver packages to emit DB-specific `COALESCE` expressions |
| `Markommerce\Scope\Registry\ScopeRegistryInterface` | Interface for scope axis/hierarchy providers |
| `Markommerce\Scope\Axis\ScopeAxis` | Value object representing a configured axis; exposes `$name`, `$hierarchy`, and `$default` (the axis's root/global scope path) |
| `Markommerce\Scope\Hierarchy\ScopeHierarchy` | Ordered list of declared paths; provides `walkUp()` for fallback traversal |
| `Markommerce\Scope\Exceptions\InvalidSignatureException` | Thrown when a `ScopeSignature` is constructed with invalid input |
| `Markommerce\Scope\Exceptions\InvalidSignatureForAttributeException` | Thrown when signature axes do not match the target property's `#[Scoped]` attribute, or when a signature names an axis at its default scope |
| `Markommerce\Scope\Exceptions\ScopeConfigurationException` | Thrown at boot when an axis definition is malformed, missing `default`, declares an empty `scopes` map, or names a `default` path absent from `scopes` |
| `Markommerce\Scope\Exceptions\ScopeStorageException` | Thrown by `setOverride()`/`clearOverride()` when attempting to write an override at an axis's default scope |
| `Markommerce\Scope\Exceptions\MultiAxisWalkAtNotSupportedException` | Thrown when `walkAt()` is called with a multi-axis signature |

### `ScopeContext`

| Method | Description |
|--------|-------------|
| `in(string $axis, string $path): static` | Set the active path for an axis. Throws `UnknownAxisException` or `ScopeContextException` if the axis or path is invalid. |
| `get(string $axis): ?string` | Return the active path for an axis, or `null` if not set. |
| `clear(string $axis): void` | Remove the active path for an axis. |
| `clearAll(): void` | Remove all active paths. Call between requests in long-running processes. |
| `activeAxes(): list<string>` | Return the names of all axes that have an active path. |
| `registry(): ScopeRegistryInterface` | Return the registry this context was constructed with. |
| `state(): array<string, string>` | Return the full axis-name → active-path map for the current context. |

### `ScopeResolver`

| Method | Description |
|--------|-------------|
| `resolved(Entity $entity, string $property): mixed` | Walk the active context hierarchy and return the most specific override, falling back to the column value. |
| `resolvedAt(Entity $entity, string $property, ScopeSignature $signature): mixed` | Resolve at a specific scope regardless of the active context. |
| `setOverride(Entity $entity, string $property, mixed $value, ScopeSignature $signature): void` | Attach a scoped value to the entity. The entity or one of its companions must implement `HasScopesInterface`. |
| `clearOverride(Entity $entity, string $property, ScopeSignature $signature): void` | Remove a scoped override from the entity. |

### `ScopeSignature`

| Method | Description |
|--------|-------------|
| `fromArray(array $axisValues): self` | Static factory; construct from an associative array of axis → path pairs. |
| `fromString(string $signature): self` | Static factory; parse from a serialized string like `channel:b2b\|locale:es`. |
| `toString(): string` | Return the serialized string representation. |
| `equals(ScopeSignature $other): bool` | Return `true` if both signatures are identical. |
| `hasAxis(string $axis): bool` | Return `true` if the signature contains the given axis. |
| `get(string $axis): ?string` | Return the path for the given axis, or `null` if not present. |
| `axes(): list<string>` | Return all axis names in sorted order. |

### `ScopedOrderByFactory`

| Method | Description |
|--------|-------------|
| `create(string $entityClass, string $property, string $direction = 'asc'): ScopedOrderBy` | Build a `QuerySpecification` that orders by the resolved scope value for the active context. |

### `ScopeAxis`

| Property | Type | Description |
|----------|------|-------------|
| `$name` | `string` | The axis name as declared in config (e.g. `locale`, `market`, `channel`). |
| `$hierarchy` | `ScopeHierarchy` | All declared scope paths for this axis. |
| `$default` | `string` | The root/global scope path for this axis. Overrides at this path are rejected by `DefaultScopeGuard`. |

### `ScopeHierarchy`

| Method | Description |
|--------|-------------|
| `fromPaths(list<string> $paths): self` | Build a hierarchy from a flat list of dotted paths. |
| `paths(): list<string>` | Return all declared paths in declaration order. |
| `exists(string $path): bool` | Check whether a path is declared. |
| `isAncestor(string $ancestor, string $descendant): bool` | Return true if `$ancestor` is a strict ancestor of `$descendant`. |
| `walkUp(string $path): list<string>` | Return the path and all ancestors in deepest-first order. |

### `DefaultScopeGuard`

| Method | Description |
|--------|-------------|
| `configure(array $axisDefaults): void` | (Static) Provide the axis → default-scope map. Called once at module boot. |
| `assertWritable(string $signature): void` | (Static) Throw `ScopeStorageException` if the signature contains any axis at its configured default scope. |
| `reset(): void` | (Static) Clear the configured defaults. Intended for testing only. |
| `isConfigured(): bool` | (Static) Return `true` if defaults have been configured. |

## Caveats

**`ScopeContext` is a mutable singleton.** It holds active paths for the entire PHP process lifetime. In long-running processes (FPM workers, queue daemons, ReactPHP servers), the bootstrap layer must call `$scopeContext->clearAll()` between requests or jobs to prevent cross-request scope leakage.

**Writing to the default scope is an error.** The axis `default` (e.g. `locale:default`) represents the base entity property. Passing a signature that names any axis at its configured default scope to `setOverride()` or `clearOverride()` throws `ScopeStorageException`. Edit the entity's base property directly instead. The same restriction applies to `ScopeSignatureValidator::validate()`, which throws `InvalidSignatureForAttributeException::forDefaultScope` in this case.

**Default-scope values are never enumerated as candidates.** `SignatureCandidateEnumerator` strips default-scope paths from the walk-up results. If all active context paths happen to equal their axis defaults, the enumerator returns no candidates and resolution falls through to the base column value without a DB lookup.

**`walkAt` is single-axis only.** `ScopeWalker::walkAt()` accepts only a single-axis `ScopeSignature`. Passing a multi-axis signature throws `MultiAxisWalkAtNotSupportedException`. Use `walk()` with a `ScopeContext` for multi-axis resolution.

**Terminology overlap with `marko/config`.** The `marko/config` package uses the term "tenant scope" as a configuration parameter name. This is unrelated to `markommerce/scope`'s axis/path concept --- the two systems are independent.

## Related Packages

- [markommerce/scope-pgsql](/docs/packages/scope-pgsql/) --- PostgreSQL driver
- [marko/database](https://marko.build/docs/packages/database/) --- Entity system and `QuerySpecification` interface
