---
title: markommerce/catalog-attribute-scope
description: Scoped product attribute value storage — adds per-scope overrides to Product attribute values via a companion entity and a typed accessor.
---

Scope storage bridge for catalog attribute entities. `markommerce/catalog-attribute-scope` adds per-scope attribute value overrides to `Product` without forking the base attribute accessor. It ships a companion entity (`ProductScopedAttributeValues`) that extends the `catalog_products` table with a `scoped_attribute_values` JSON column, and a `ScopedProductAttributeAccessor` that handles both JSON-backed (custom) and column-backed (static) attributes. JSON-backed values follow a scoped-override-then-global-fallback chain; column-backed values are resolved via the scope kernel's `ScopeResolver` over the native property. This package does not depend on `markommerce/catalog-scope`.

## Installation

```bash
composer require markommerce/catalog-attribute-scope
```

The module requires `markommerce/catalog-attribute`, `markommerce/attribute`, `markommerce/catalog`, and `markommerce/scope` (all pulled in as transitive dependencies). A PostgreSQL storage driver for attributes is also needed:

```bash
composer require markommerce/attribute-pgsql
```

## Usage

### How it wires itself in

The module's `boot` closure merges `ProductScopedAttributeValues` into the list of extenders for `Product` via `EntityMetadataFactory::linkExtenders()`. No manual wiring is required --- the `scoped_attribute_values` companion column is added automatically alongside `catalog_products`.

### Writing a scoped attribute value

Inject `ScopedProductAttributeAccessor` and call `setScoped()`. The method validates and casts the value via the Phase-1 `AttributeValueValidator`, then stores the result in the companion blob (JSON-backed) or delegates to `ScopeResolver::setOverride()` (column-backed):

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogAttributeScope\ScopedProductAttributeAccessor;
use Markommerce\Scope\Signature\ScopeSignature;

class ProductAttributeLocaliser
{
    public function __construct(
        private ScopedProductAttributeAccessor $scopedProductAttributeAccessor,
    ) {}

    public function localise(Product $product): void
    {
        $deSignature = ScopeSignature::fromArray(['locale' => 'de']);

        // JSON-backed custom attribute: value validated/cast, stored in companion blob
        $this->scopedProductAttributeAccessor->setScoped(
            $product,
            'description',
            'Produktbeschreibung auf Deutsch',
            $deSignature,
        );

        // Persist via the standard repository
        // $productRepository->save($product);
    }
}
```

`setScoped()` throws:
- `AttributeDefinitionNotFoundException` --- if `$code` is not defined for product entities.
- `ScopeContextException` --- if the attribute is not marked as `scopable`, or (for column-backed attributes) if the native property is not registered as a scoped field.

### Reading a scoped override at a specific signature

`getScoped()` returns the raw stored override for a precise signature. It does not walk ancestors --- use `resolve()` instead for active-scope resolution:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogAttributeScope\ScopedProductAttributeAccessor;
use Markommerce\Scope\Signature\ScopeSignature;

class ProductAttributeReader
{
    public function __construct(
        private ScopedProductAttributeAccessor $scopedProductAttributeAccessor,
    ) {}

    public function rawOverride(Product $product): mixed
    {
        $deSignature = ScopeSignature::fromArray(['locale' => 'de']);

        // Returns the stored value for exactly this signature, or null if absent
        return $this->scopedProductAttributeAccessor->getScoped(
            $product,
            'description',
            $deSignature,
        );
    }
}
```

### Resolving the effective value for the active scope

`resolve()` is the primary read path for rendering. For JSON-backed attributes it walks the companion overrides from most-specific to least-specific (using `ScopeWalker` over axes from `config['axes']`), then falls back to the global value from `ProductAttributeAccessor`. For column-backed (static) attributes it delegates to `ScopeResolver::resolved()` over the native property:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogAttributeScope\ScopedProductAttributeAccessor;

class ProductAttributeView
{
    public function __construct(
        private ScopedProductAttributeAccessor $scopedProductAttributeAccessor,
    ) {}

    public function effectiveDescription(Product $product): mixed
    {
        // Most-specific scoped override → global value fallback
        return $this->scopedProductAttributeAccessor->resolve($product, 'description');
    }
}
```

### JSON-backed vs column-backed attributes

| Backing | Write path | Read path (`resolve`) |
|---|---|---|
| `Json` | Stores override in `ProductScopedAttributeValues` companion blob | `ScopeWalker` over companion blob → global `ProductAttributeAccessor::get()` fallback |
| `Column` | `ScopeResolver::setOverride()` on the native property | `ScopeResolver::resolved()` on the native property |

Column-backed (static) attributes (`sku`, `name`, `priceAmount`) are resolved via the generic scope kernel's `ScopeResolver`. A scoped write to a native property that has no scoped-field registration throws `ScopeContextException` with an actionable suggestion. To enable scoped native-field overrides, install the native-field scoping bridge (`markommerce/catalog-scope` with `catalog-locale` or `catalog-market`).

### Non-scopable attribute guard

Calling `setScoped()` on an attribute whose `$scopable` flag is `false` throws immediately:

```php
// AttributeDefinition::$scopable must be true
// Throws ScopeContextException with suggestion to mark the attribute as scopable
$accessor->setScoped($product, 'internal_ref', 'DE-001', $signature);
```

### Declaring scope axes on an attribute definition

The `resolve()` method reads `config['axes']` from the `AttributeDefinition`. Declare axes when creating the definition:

```php
<?php

declare(strict_types=1);

use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Services\AttributeDefinitionService;

class DescriptionAttributeInstaller
{
    public function __construct(
        private AttributeDefinitionService $attributeDefinitionService,
    ) {}

    public function run(): void
    {
        $definition = new AttributeDefinition();
        $definition->code = 'description';
        $definition->entityType = 'product';
        $definition->type = 'text';
        $definition->label = 'Description';
        $definition->scopable = true;
        $definition->config = ['axes' => ['locale']];

        $this->attributeDefinitionService->create($definition);
    }
}
```

## API Reference

### `ScopedProductAttributeAccessor`

Typed accessor for per-scope product attribute overrides. Does not implement `AttributeValueAccessorInterface` --- it is a purpose-built scoped layer on top of `ProductAttributeAccessor`.

| Method | Description |
|---|---|
| `setScoped(Product $product, string $code, mixed $raw, ScopeSignature $signature): void` | Validate, cast, and store a scoped override. Throws `AttributeDefinitionNotFoundException` if the code is unknown, or `ScopeContextException` if the attribute is not scopable or (for column-backed attributes) if the native property has no scoped-field registration. |
| `getScoped(Product $product, string $code, ScopeSignature $signature): mixed` | Return the raw stored override for the exact signature. Returns `null` if absent. Throws `AttributeDefinitionNotFoundException`. |
| `resolve(Product $product, string $code): mixed` | Return the effective value for the active `ScopeContext`. For JSON-backed attributes: most-specific scoped override → global fallback. For column-backed attributes: `ScopeResolver::resolved()` on the native property. Throws `AttributeDefinitionNotFoundException`. |

### `ProductScopedAttributeValues`

Companion entity (`#[Table(extends: Product::class)]`) that stores per-scope attribute value overrides in a `scoped_attribute_values` JSON column. Implements `HasScopesInterface`. The JSON blob shape is `{signature: {code: value}}`.

| Method | Description |
|---|---|
| `setOverride(string $signature, string $property, mixed $value): void` | Store a scoped override. Throws `ScopeStorageException` if `$signature` is `'default'`. |
| `override(string $signature, string $property): mixed` | Return the stored override value, or `null` if not set. |
| `hasOverride(string $signature, string $property): bool` | Check whether an override exists for the given signature and property. |
| `clearOverride(string $signature, string $property): void` | Remove an override. Throws `ScopeStorageException` if `$signature` is `'default'`. No-op if the entry does not exist. |
| `overrides(): array<string, array<string, mixed>>` | Return all stored overrides as a nested `{signature: {code: value}}` map. |

### Exceptions

| Exception | When thrown |
|---|---|
| `AttributeDefinitionNotFoundException` | The requested attribute code is not defined for the `product` entity type. |
| `ScopeContextException` | The attribute is not scopable (`$scopable === false`), or a column-backed attribute's native property is not registered as a scoped field. |
| `ScopeStorageException` | `setOverride()` or `clearOverride()` was called with `'default'` as the signature. |

## Related Packages

- [markommerce/attribute](/docs/packages/attribute/) --- attribute kernel: type registry, definition service, and value validation
- [markommerce/catalog-attribute](/docs/packages/catalog-attribute/) --- global (non-scoped) product attribute accessor; Phase-2 foundation this package builds on
- [markommerce/attribute-pgsql](/docs/packages/attribute-pgsql/) --- PostgreSQL storage driver
- [markommerce/scope](/docs/packages/scope/) --- scope kernel: `ScopeContext`, `ScopeWalker`, `ScopeResolver`, `HasScopesInterface`
- [markommerce/attribute-scope](/docs/packages/attribute-scope/) --- scoped option label resolution (the `AttributeOption`-side counterpart)
- [markommerce/catalog-scope](/docs/packages/catalog-scope/) --- native-field scoping bridge for `Product`; required if you want scoped writes to column-backed (static) attributes
- [markommerce/catalog-attribute-index](/docs/packages/catalog-attribute-index/) --- denormalized EAV read-model built on top of this package's `ScopedProductAttributeAccessor`
