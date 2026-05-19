---
title: markommerce/scope
description: Scoped entity attributes with multi-axis hierarchical fallback.
---

Scoped attributes for entities with multi-axis hierarchical fallback. `markommerce/scope` defines the contracts and core logic for attaching per-scope override values to entity properties. Each property marked `#[Scoped]` can carry different values across multiple independent axes (e.g. `locale`, `market`, `channel`), with automatic walk-up through the declared hierarchy when no exact match exists. The package ships the `#[Scoped]` attribute, `ScopeContext`, `ScopeResolver`, `HasScopesInterface`, the `HasScopes` trait, and the `ScopedOrderBy` query specification --- but no database driver. Applications must install `markommerce/scope-pgsql` to persist and query overrides. Scope storage is provided by the `HasScopes` trait: entities implement `HasScopesInterface` and include the trait, which declares a `$scopes` JSON column automatically.

## Installation

```bash
composer require markommerce/scope
```

No sort renderer is bound by default. You must also install a driver package:

```bash
composer require markommerce/scope-pgsql
```

## Configuration

Declare axes and their path hierarchies in `config/scope.php`:

```php title="config/scope.php"
<?php

declare(strict_types=1);

return [
    'axes' => [
        'locale' => [
            'hierarchy' => [
                'en',
                'de',
                'de-DE',
                'de-AT',
                'fr',
                'fr-FR',
                'fr-BE',
            ],
        ],
        'market' => [
            'hierarchy' => [
                'eu',
                'eu.de',
                'eu.fr',
                'eu.at',
                'us',
                'us.east',
                'us.west',
            ],
        ],
    ],
];
```

Paths use dot notation. `walkUp('eu.de')` yields `['eu.de', 'eu']`, so a value set at `eu` is inherited by `eu.de` when no `eu.de`-specific override exists.

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
    #[Scoped(axes: ['market'])]
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
$context->in('market', 'eu.fr');
```

### Writing overrides

Use `ScopeResolver::setOverride()` to attach a scoped value to an entity before persisting:

```php
<?php

declare(strict_types=1);

use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Scope;

$product = new Product();
$product->name = 'Widget';
$product->price = 100.00;

// Set a German locale override for the name
$scopeResolver->setOverride($product, 'name', 'Widget DE', new Scope('locale', 'de'));

// Set market overrides for price at different hierarchy levels
$scopeResolver->setOverride($product, 'price', 89.99, new Scope('market', 'eu'));
$scopeResolver->setOverride($product, 'price', 79.99, new Scope('market', 'eu.de'));

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
use Markommerce\Scope\Scope;

$dePrice = $scopeResolver->resolvedAt($product, 'price', new Scope('market', 'eu.de')); // 79.99
```

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
use Markommerce\Scope\Scope;

$scopeResolver->clearOverride($product, 'price', new Scope('market', 'eu.de'));
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
| `Markommerce\Scope\Storage\HasScopesInterface` | Interface for entities that store scoped overrides directly via the `HasScopes` trait |
| `Markommerce\Scope\Storage\HasScopes` | Trait that adds a `$scopes` JSON column and the override storage methods. The consuming class must also declare `implements HasScopesInterface` --- PHP does not allow traits to enforce interface implementation. |
| `Markommerce\Scope\Query\ScopedOrderBy` | `QuerySpecification` that orders by resolved scope value |
| `Markommerce\Scope\Query\ScopedOrderByFactory` | Factory for building `ScopedOrderBy` specifications |
| `Markommerce\Scope\Query\ScopeSortRendererInterface` | Interface implemented by driver packages to emit DB-specific `COALESCE` expressions |
| `Markommerce\Scope\Registry\ScopeRegistryInterface` | Interface for scope axis/hierarchy providers |
| `Markommerce\Scope\Scope` | Value object representing a single axis+path pair (`axis:path`) |
| `Markommerce\Scope\Hierarchy\ScopeHierarchy` | Ordered list of declared paths; provides `walkUp()` for fallback traversal |

### `ScopeContext`

| Method | Description |
|--------|-------------|
| `in(string $axis, string $path): static` | Set the active path for an axis. Throws `UnknownAxisException` or `ScopeContextException` if the axis or path is invalid. |
| `get(string $axis): ?string` | Return the active path for an axis, or `null` if not set. |
| `clear(string $axis): void` | Remove the active path for an axis. |
| `clearAll(): void` | Remove all active paths. Call between requests in long-running processes. |
| `activeAxes(): list<string>` | Return the names of all axes that have an active path. |
| `registry(): ScopeRegistryInterface` | Return the registry this context was constructed with. |

### `ScopeResolver`

| Method | Description |
|--------|-------------|
| `resolved(Entity $entity, string $property): mixed` | Walk the active context hierarchy and return the most specific override, falling back to the column value. |
| `resolvedAt(Entity $entity, string $property, Scope $scope): mixed` | Resolve at a specific scope regardless of the active context. |
| `setOverride(Entity $entity, string $property, mixed $value, Scope $scope): void` | Attach a scoped value to the entity. The entity or one of its companions must implement `HasScopesInterface`. |
| `clearOverride(Entity $entity, string $property, Scope $scope): void` | Remove a scoped override from the entity. |

### `ScopedOrderByFactory`

| Method | Description |
|--------|-------------|
| `create(string $entityClass, string $property, string $direction = 'asc'): ScopedOrderBy` | Build a `QuerySpecification` that orders by the resolved scope value for the active context. |

### `ScopeHierarchy`

| Method | Description |
|--------|-------------|
| `fromPaths(list<string> $paths): self` | Build a hierarchy from a flat list of dotted paths. |
| `paths(): list<string>` | Return all declared paths in declaration order. |
| `exists(string $path): bool` | Check whether a path is declared. |
| `isAncestor(string $ancestor, string $descendant): bool` | Return true if `$ancestor` is a strict ancestor of `$descendant`. |
| `walkUp(string $path): list<string>` | Return the path and all ancestors in deepest-first order. |

## Caveats

**`ScopeContext` is a mutable singleton.** It holds active paths for the entire PHP process lifetime. In long-running processes (FPM workers, queue daemons, ReactPHP servers), the bootstrap layer must call `$scopeContext->clearAll()` between requests or jobs to prevent cross-request scope leakage.

**Terminology overlap with `marko/config`.** The `marko/config` package uses the term "tenant scope" as a configuration parameter name. This is unrelated to `markommerce/scope`'s axis/path concept --- the two systems are independent.

## Related Packages

- [markommerce/scope-pgsql](/docs/packages/scope-pgsql/) --- PostgreSQL driver
- [marko/database](https://marko.build/docs/packages/database/) --- Entity system and `QuerySpecification` interface
