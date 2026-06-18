---
title: markommerce/scope
description: Scoped entity attributes with multi-axis hierarchical fallback.
---

Scoped attributes for entities with multi-axis hierarchical fallback. `markommerce/scope` defines the contracts and core logic for attaching per-scope override values to entity properties. Each property marked `#[Scoped]` can carry different values across multiple independent axes (e.g. `locale`, `market`, `channel`), with automatic walk-up through the declared hierarchy when no exact match exists. The package ships the `#[Scoped]` attribute, `ScopeContext`, `ScopeResolver`, `ScopeSignature`, `HasScopesInterface`, the `HasScopes` trait, and `ScopedOrderBy` query specification. The PostgreSQL implementation (JSONB storage, GIN index, scoped `ORDER BY`) is bundled directly — no separate driver package is required. Scope storage is provided by the `HasScopes` trait: entities implement `HasScopesInterface` and include the trait, which declares a `$scopes` JSON column automatically.

## Installation

```bash
composer require markommerce/scope
```

The package ships its PostgreSQL implementation directly. No additional driver package is required.

## Configuration

Declare axes in `config/scope.php`. Each axis requires a `default` key naming the root/global scope (where the base entity property value lives) and a `scopes` map listing every valid scope path:

```php title="config/scope.php"
<?php

declare(strict_types=1);

return [
    'axes' => [
        // The locale axis is contributed by markommerce/locale.
        // Copy this block into your app's config/scope.php and extend it.
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

The package ships a minimal `config/scope.php` with `market` and `channel` axes as a starting point. Extend it with the scope paths your application needs. The `locale` axis is contributed by the separate [markommerce/locale](/docs/packages/locale/) package --- install it to make the `locale` axis available.

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

### Field metadata

`ScopedFieldRegistry` is the read-time source of truth for which properties of an entity class are scoped and which axes they belong to. `ScopeMetadataFactory` reads from the registry to build `ScopeMetadata` instances; consumers call `ScopeMetadataFactory::for($entityClass)` to retrieve metadata for a class. The public API of `ScopeMetadataFactory` is unchanged.

#### Attribute path (lazy scan)

The simplest way to mark a property as scoped is the `#[Scoped]` attribute. On the first call to `ScopeMetadataFactory::for($entityClass)`, the factory performs a one-time reflection scan of the class and its parents, writing any `#[Scoped]` findings into `ScopedFieldRegistry`. Subsequent calls return the cached `ScopeMetadata` without re-scanning.

A `#[Scoped(axes: [])]` declaration is a no-op: the property will not appear in the resulting `ScopeMetadata`.

#### Programmatic path (bridge `module.php`)

Third-party packages that cannot annotate an entity directly --- for example, a bridge package adding scope support to a vendor entity --- register properties programmatically via a `boot` callback in their `module.php`. The container auto-injects `ScopedFieldRegistry` by type-hint:

```php title="packages/acme-catalog-scope/module.php"
<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Product;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;

return [
    'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
        $scopedFieldRegistry->register(
            entityClass: Product::class,
            property: 'price',
            axes: ['channel', 'locale'],
        );
    },
];
```

Passing `axes: []` to `register()` is a no-op: the call returns without modifying the registry.

If the `$entityClass` argument does not resolve to an existing class, interface, or enum, `register()` throws `UnknownEntityClassException`. If any axis name is not registered in `ScopeRegistryInterface`, `register()` throws `UnknownAxisException` --- there are no silent failures.

#### Cache-staleness contract

Once `ScopeMetadataFactory::for($entityClass)` has been called for a class, the resulting `ScopeMetadata` is frozen. Later calls to `ScopedFieldRegistry::register()` for the same class do NOT affect the already-cached metadata. All bridge contributions must happen during the boot phase, before any request handling begins.

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

## Scope Resolution

Before user code runs, `ScopeResolutionPipeline` resolves the current scope path for every registered axis and writes the results into `ScopeContext`. Axes are processed in registration order (the order they appear in `ScopeRegistryInterface::listAxes()`). Within each axis a chain of resolvers is tried in order --- the first resolver that returns a non-null, valid path wins, and the remaining resolvers are skipped.

### Configuration

Add a `'resolvers'` key under each axis in `config/scope.php`. Each entry is either a **class-string** (resolved from the container, dependencies injected) or an **array** with a `'class'` key plus any additional constructor arguments:

```php title="config/scope.php"
<?php

declare(strict_types=1);

use Markommerce\Scope\Resolver\Resolution\Builtin\AcceptLanguageResolver;
use Markommerce\Scope\Resolver\Resolution\Builtin\CookieResolver;
use Markommerce\Scope\Resolver\Resolution\Builtin\StaticResolver;
use Markommerce\Scope\Resolver\Resolution\Builtin\SubdomainResolver;

return [
    'axes' => [
        'locale' => [
            'default'   => 'default',
            'scopes'    => ['default' => [], 'en' => [], 'de' => [], 'fr' => []],
            'resolvers' => [
                // Class-string form — instantiated via the container (full DI)
                AcceptLanguageResolver::class,

                // Array form — instantiated directly; extra keys become constructor args
                ['class' => CookieResolver::class, 'cookieName' => 'store_locale'],

                // Final fallback: always returns 'en'
                ['class' => StaticResolver::class, 'value' => 'en'],
            ],
        ],
        'channel' => [
            'default'   => 'web',
            'scopes'    => ['web' => [], 'b2b' => []],
            'resolvers' => [
                ['class' => SubdomainResolver::class, 'segment' => 0],
                ['class' => StaticResolver::class, 'value' => 'web'],
            ],
        ],
    ],
];
```

Order matters: the first resolver that returns a non-null path wins. If no resolver matches, the axis falls back to its configured `default` scope.

### Built-in resolvers

| Resolver | Constructor params | Channels | Description |
|---|---|---|---|
| `CookieResolver` | `cookieName: string` | HTTP only | Reads a named cookie from `$_COOKIE` (or an injected array for testing). |
| `HeaderResolver` | `headerName: string` | HTTP only | Reads a named HTTP request header. |
| `SubdomainResolver` | `segment: int = 0` | HTTP only | Reads a segment of the `Host` header split by `.` (0 = leftmost subdomain). No-ops on raw IPs. |
| `PathPrefixResolver` | `segment: int = 0` | HTTP only | Reads a segment of the URL path split by `/` (0 = first path component). |
| `QueryParamResolver` | `paramName: string` | HTTP only | Reads a named query-string parameter. |
| `AcceptLanguageResolver` | _(none)_ | HTTP only | Parses the `Accept-Language` header (RFC 7231, q-values) and returns the highest-preference language tag that exists in the axis hierarchy; falls back to the bare language code (without region) if needed. |
| `StaticResolver` | `value: string` | Universal | Always returns the configured value regardless of channel. Use as a final fallback. |

HTTP-only resolvers return `null` immediately when the channel is not `CHANNEL_HTTP`, so the same resolver chain works unchanged on CLI and queue channels.

### Channel semantics

The pipeline runs with one of three channel constants from `ScopeResolutionContext`:

| Constant | Trigger |
|---|---|
| `CHANNEL_HTTP` | Every incoming HTTP request |
| `CHANNEL_CLI` | Every CLI command (`CommandInterface::execute`) |
| `CHANNEL_QUEUE` | Queue jobs that opt in via `JobScopeWrapper::withScope()` |

HTTP-only resolvers (`CookieResolver`, `HeaderResolver`, `SubdomainResolver`, `PathPrefixResolver`, `QueryParamResolver`, `AcceptLanguageResolver`) no-op when the channel is not `CHANNEL_HTTP`. `StaticResolver` is channel-agnostic and works everywhere.

### Cross-axis dependencies

Axes resolve in registration order. Each resolver receives a `ScopeResolutionContext` that includes a `$resolved` map containing the paths already committed for all preceding axes. Use this to make one axis conditional on another:

```php
<?php

declare(strict_types=1);

use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Resolver\Resolution\ScopeAxisResolverInterface;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;

readonly class ChannelDependentMarketResolver implements ScopeAxisResolverInterface
{
    public function resolve(ScopeAxis $scopeAxis, ScopeResolutionContext $scopeResolutionContext): ?string
    {
        // Read the already-resolved channel axis
        $channel = $scopeResolutionContext->resolved['channel'] ?? null;

        if ($channel === 'b2b') {
            return 'eu';
        }

        return null; // defer to next resolver
    }
}
```

### Lifecycle hooks

The pipeline is wired into three application lifecycle points:

#### HTTP --- automatic via global middleware (marko ≥ TBD required) <!-- TODO: fill in marko version after release -->

`ScopeResolutionMiddleware` is declared in `packages/scope/module.php` as a global middleware entry with priority 5. Marko's module system picks it up automatically --- no manual registration is needed. The middleware runs the pipeline before the controller and clears `ScopeContext` in a `finally` block after the response is produced, preventing cross-request leakage in FPM and long-running server processes.

#### CLI --- automatic via plugin on `CommandInterface`

`ScopeResolutionCommandPlugin` applies a `#[Before]` and `#[After]` intercept to every `CommandInterface::execute()` call. The `#[Before]` hook defensively calls `clear()` first (see caveat below), then runs the pipeline with `CHANNEL_CLI`. The `#[After]` hook clears `ScopeContext` when the command exits normally.

**Caveat:** `#[After]` does not run when a command throws an uncaught exception --- this is a known limitation of Marko's plugin chain. The `#[Before]` hook compensates by calling `clear()` at the start of every command, ensuring a clean context even if the previous command crashed. CLI processes are typically short-lived (one command per invocation), so a leaked context is lost when the process exits.

#### Queue --- manual opt-in via `JobScopeWrapper`

Marko's queue `Worker` deserializes jobs and calls `handle()` directly, bypassing the container --- plugin auto-wiring is not possible for queue jobs. Applications must explicitly wrap job logic with `JobScopeWrapper::withScope()`:

```php
<?php

declare(strict_types=1);

use Markommerce\Scope\Queue\JobScopeWrapper;

class SendOrderConfirmationJob
{
    public function __construct(
        private readonly JobScopeWrapper $jobScopeWrapper,
        private readonly int $orderId,
    ) {}

    public function handle(): void
    {
        $this->jobScopeWrapper->withScope(function (): void {
            // ScopeContext is populated here with CHANNEL_QUEUE
            $this->doActualWork();
        });
    }
}
```

`withScope()` clears any previously-leaked context first, runs the pipeline with `CHANNEL_QUEUE`, executes the callable, and clears context in a `finally` block regardless of whether the callable throws.

### Error behavior

Resolver failures are never fatal. If a resolver throws, the pipeline:

1. Wraps the exception in `ScopeResolutionException::resolverFailed()`.
2. Logs the error via `LoggerInterface` if one is bound (silent otherwise).
3. Continues to the next resolver in the chain.

Invalid paths (a resolver returns a string that does not exist in the axis hierarchy) are treated the same way: logged and skipped. If the entire chain produces no valid path, the axis falls back to its configured `default`.

### Writing a custom resolver

Implement `ScopeAxisResolverInterface`. Return a string path when your resolver can determine the scope, or `null` to defer to the next resolver in the chain:

```php
<?php

declare(strict_types=1);

namespace App\Scope\Resolver;

use Marko\Routing\Http\Request;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Resolver\Resolution\ScopeAxisResolverInterface;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;

readonly class CustomerGroupResolver implements ScopeAxisResolverInterface
{
    public function __construct(
        private Request $request,
    ) {}

    public function resolve(ScopeAxis $scopeAxis, ScopeResolutionContext $scopeResolutionContext): ?string
    {
        if ($scopeResolutionContext->channel !== ScopeResolutionContext::CHANNEL_HTTP) {
            return null;
        }

        // Read a custom header set by your authentication middleware
        $group = $scopeResolutionContext->request->header('X-Customer-Group');

        if ($group === null || $group === '') {
            return null;
        }

        // Return the value only if it exists in the axis hierarchy
        if ($scopeAxis->hierarchy->exists($group)) {
            return $group;
        }

        return null;
    }
}
```

Register it in `config/scope.php`:

```php title="config/scope.php"
<?php

declare(strict_types=1);

use App\Scope\Resolver\CustomerGroupResolver;

return [
    'axes' => [
        'channel' => [
            'default'   => 'web',
            'scopes'    => ['web' => [], 'b2b' => [], 'b2c' => []],
            'resolvers' => [
                CustomerGroupResolver::class,
            ],
        ],
    ],
];
```

If the resolver needs constructor arguments that are not in the container, use the array form instead:

```php
'resolvers' => [
    ['class' => CustomerGroupResolver::class, 'headerName' => 'X-Customer-Group'],
],
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
| `Markommerce\Scope\Metadata\ScopedFieldRegistry` | Accumulates (entityClass, property) → axes mappings at boot time; populated by attribute scanning and programmatic `register()` calls; registered as a singleton |
| `Markommerce\Scope\Exceptions\UnknownEntityClassException` | Thrown by `ScopedFieldRegistry::register()` when the given class name does not resolve to an existing class, interface, or enum |
| `Markommerce\Scope\Exceptions\InvalidSignatureException` | Thrown when a `ScopeSignature` is constructed with invalid input |
| `Markommerce\Scope\Exceptions\InvalidSignatureForAttributeException` | Thrown when signature axes do not match the target property's `#[Scoped]` attribute, or when a signature names an axis at its default scope |
| `Markommerce\Scope\Exceptions\ScopeConfigurationException` | Thrown at boot when an axis definition is malformed, missing `default`, declares an empty `scopes` map, or names a `default` path absent from `scopes` |
| `Markommerce\Scope\Exceptions\ScopeStorageException` | Thrown by `setOverride()`/`clearOverride()` when attempting to write an override at an axis's default scope |
| `Markommerce\Scope\Exceptions\MultiAxisWalkAtNotSupportedException` | Thrown when `walkAt()` is called with a multi-axis signature |
| `Markommerce\Scope\Exceptions\InvalidResolverConfigException` | Thrown at boot (or first use) when a resolver entry in `config/scope.php` references a non-existent class, is missing the `'class'` key, or the class does not implement `ScopeAxisResolverInterface` |
| `Markommerce\Scope\Exceptions\ScopeResolutionException` | Thrown (and caught internally) when a resolver fails or returns an invalid path; logged and skipped, never propagated to user code |
| `Markommerce\Scope\Resolver\Resolution\ScopeAxisResolverInterface` | Implement this to write a custom resolver; `resolve()` returns a scope path string or `null` to defer |
| `Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext` | Passed to every resolver; exposes `$channel`, `$request`, `$registry`, and `$resolved` (already-resolved axis map) |
| `Markommerce\Scope\Resolver\Resolution\ScopeResolutionPipeline` | Iterates registered axes, runs the resolver chain, and writes results into `ScopeContext` |
| `Markommerce\Scope\Resolver\Resolution\ScopeResolverChainFactory` | Builds the resolver chain for a given axis from `config/scope.php`; results are cached per axis |
| `Markommerce\Scope\Resolver\Resolution\Builtin\CookieResolver` | Reads a named cookie (HTTP only) |
| `Markommerce\Scope\Resolver\Resolution\Builtin\HeaderResolver` | Reads a named HTTP request header (HTTP only) |
| `Markommerce\Scope\Resolver\Resolution\Builtin\SubdomainResolver` | Reads a subdomain segment from the `Host` header (HTTP only) |
| `Markommerce\Scope\Resolver\Resolution\Builtin\PathPrefixResolver` | Reads a URL path segment (HTTP only) |
| `Markommerce\Scope\Resolver\Resolution\Builtin\QueryParamResolver` | Reads a query-string parameter (HTTP only) |
| `Markommerce\Scope\Resolver\Resolution\Builtin\AcceptLanguageResolver` | Parses `Accept-Language` and matches against the axis hierarchy (HTTP only) |
| `Markommerce\Scope\Resolver\Resolution\Builtin\StaticResolver` | Returns a fixed value regardless of channel (universal) |
| `Markommerce\Scope\Middleware\ScopeResolutionMiddleware` | HTTP global middleware that runs the pipeline before the controller and clears context in a `finally` block |
| `Markommerce\Scope\Plugins\ScopeResolutionCommandPlugin` | Plugin on `CommandInterface` that runs the pipeline before CLI command execution and clears context afterward |
| `Markommerce\Scope\Queue\JobScopeWrapper` | Manual opt-in helper for queue jobs; call `withScope(callable $work)` inside `handle()` |

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

### `ScopedFieldRegistry`

| Method | Description |
|--------|-------------|
| `register(string $entityClass, string $property, array $axes): void` | Register a (class, property) → axes mapping. Throws `UnknownEntityClassException` if the class does not exist; throws `UnknownAxisException` if any axis name is unknown. Calling with `axes: []` is a no-op. Axes from multiple calls for the same property are merged (union, no duplicates). |
| `axesForProperty(string $entityClass, string $property): list<string>` | Return the axes registered for a specific entity class + property pair, or an empty list if none are registered. |
| `propertiesFor(string $entityClass): array<string, list<string>>` | Return all registered property → axes mappings for the given entity class. |
| `hasScopedProperties(string $entityClass): bool` | Return `true` if at least one property is registered for the given entity class. |

## Caveats

**`ScopeContext` is a mutable singleton.** It holds active paths for the entire PHP process lifetime. In long-running processes (FPM workers, queue daemons, ReactPHP servers), the bootstrap layer must call `$scopeContext->clearAll()` between requests or jobs to prevent cross-request scope leakage.

**Writing to the default scope is an error.** The axis `default` (e.g. `locale:default`) represents the base entity property. Passing a signature that names any axis at its configured default scope to `setOverride()` or `clearOverride()` throws `ScopeStorageException`. Edit the entity's base property directly instead. The same restriction applies to `ScopeSignatureValidator::validate()`, which throws `InvalidSignatureForAttributeException::forDefaultScope` in this case.

**Default-scope values are never enumerated as candidates.** `SignatureCandidateEnumerator` strips default-scope paths from the walk-up results. If all active context paths happen to equal their axis defaults, the enumerator returns no candidates and resolution falls through to the base column value without a DB lookup.

**`walkAt` is single-axis only.** `ScopeWalker::walkAt()` accepts only a single-axis `ScopeSignature`. Passing a multi-axis signature throws `MultiAxisWalkAtNotSupportedException`. Use `walk()` with a `ScopeContext` for multi-axis resolution.

**Terminology overlap with `marko/config`.** The `marko/config` package uses the term "tenant scope" as a configuration parameter name. This is unrelated to `markommerce/scope`'s axis/path concept --- the two systems are independent.

## Related Packages

- [markommerce/catalog-locale](/docs/packages/catalog-locale/) --- Canonical bridge example: registers catalog `Product` and `Category` fields as locale-scoped via `ScopedFieldRegistry` at boot
- [markommerce/locale](/docs/packages/locale/) --- Declares the `locale` axis for the scope system
- [markommerce/catalog-scope](/docs/packages/catalog-scope/) --- Adds scope storage to catalog entities via companion entities
- [marko/database](https://marko.build/docs/packages/database/) --- Entity system and `QuerySpecification` interface
