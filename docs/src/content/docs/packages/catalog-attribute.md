---
title: markommerce/catalog-attribute
description: Binds the markommerce/attribute kernel to Product — adds custom attribute values to catalog products (global scope).
---

Attribute binding for catalog entities. `markommerce/catalog-attribute` wires the `markommerce/attribute` kernel to `Product`: it registers `ProductAttributeAccessor` as the `AttributeValueAccessorInterface` implementation, adds a JSON companion column to `catalog_products` for custom values, and exposes `sku`, `name`, and `priceAmount` as static `Column`-backed attribute definitions.

## Installation

```bash
composer require markommerce/catalog-attribute
```

A storage driver is also required:

```bash
composer require markommerce/attribute-pgsql
```

## Usage

### Reading and writing attribute values

The module auto-wires `ProductAttributeAccessor` at boot. Inject `AttributeValueAccessorInterface` (or `ProductAttributeAccessor` directly) and call `set`, `get`, `all`, or `clear`:

```php
<?php

declare(strict_types=1);

use Markommerce\Attribute\Contracts\AttributeValueAccessorInterface;
use Markommerce\Catalog\Entity\Product;

class ProductAttributeExample
{
    public function __construct(
        private AttributeValueAccessorInterface $attributeValueAccessor,
    ) {}

    public function run(Product $product): void
    {
        // Set a custom attribute value (validated and cast against the definition)
        $this->attributeValueAccessor->set($product, 'color', 'red');

        // Read a value back (returns the definition's defaultValue if not stored)
        $color = $this->attributeValueAccessor->get($product, 'color');

        // Read all attributes — static (sku, name, priceAmount) plus stored custom values
        $all = $this->attributeValueAccessor->all($product);

        // Remove a custom value (no-op for Column-backed static attributes)
        $this->attributeValueAccessor->clear($product, 'color');
    }
}
```

Values are persisted when the product is saved via `ProductRepository->save($product)`.

### Static attributes

The following native `Product` columns are exposed as `Column`-backed attribute definitions. They always appear in `all()` and can be read and written via the accessor like any other attribute:

| Code | Attribute type | Backed by |
|---|---|---|
| `sku` | `text` | `Product::$sku` |
| `name` | `text` | `Product::$name` |
| `priceAmount` | `decimal` | `Product::$priceAmount` |

Static definitions are provided by `StaticAttributeProvider` and take precedence over any DB-stored definition with the same code. Writing a static attribute sets the corresponding property directly on the `Product` entity; `clear()` is a no-op for static attributes.

### Custom attribute values storage

`ProductAttributeValues` is a companion entity (`#[Table(extends: Product::class)]`) that adds an `attribute_values` JSON column to the `catalog_products` table in the same row. It holds a flat `{code: value}` map. The module links it as an extender of `Product` at boot via `EntityMetadataFactory::linkExtenders()`. No schema migration is needed beyond what `markommerce/attribute-pgsql` provides --- the companion column is added automatically.

### Reserved-code enforcement

The module registers `product → Product::class` in `AttributeEntityClassMap` during boot. This causes `AttributeDefinitionService` (from `markommerce/attribute`) to reject any custom attribute whose code matches a native `Product` column name or PHP property name (e.g. attempting to create a custom attribute with code `sku` will throw `ReservedAttributeCodeException`).

### Routing dispatch

`ProductAttributeAccessor` dispatches each operation based on the definition's `AttributeBacking`:

- **`Json`** --- value is stored in / read from the `ProductAttributeValues` companion blob.
- **`Column`** --- value is read from / written to the corresponding PHP property on the `Product` entity directly.

## API Reference

### `ProductAttributeAccessor`

Implements `AttributeValueAccessorInterface` for `Product` entities. Throws `InvalidArgumentException` if a non-`Product` entity is passed to any method.

| Method | Description |
|---|---|
| `set(object $entity, string $code, mixed $raw): void` | Validate, cast, and store a value. Dispatches to companion blob (`Json`) or entity property (`Column`). |
| `get(object $entity, string $code): mixed` | Return the stored value, falling back to the definition's `defaultValue` (cast) if absent. |
| `all(object $entity): array<string, mixed>` | Return all static attribute values plus all stored custom values as a `{code: value}` map. |
| `clear(object $entity, string $code): void` | Remove a custom value from the companion blob. No-op for `Column`-backed static attributes. |

### `ProductAttributeDefinitions`

Resolves attribute definitions for `product` entity type. Static definitions from `StaticAttributeProvider` take precedence; the repository is consulted only when no static definition matches the requested code.

| Method | Description |
|---|---|
| `findByCode(string $code): ?AttributeDefinition` | Return the definition for `$code`, checking statics first. Returns `null` if no definition exists. |

### `StaticAttributeProvider`

Provides the three hard-coded `Column`-backed attribute definitions for native product properties.

| Method | Description |
|---|---|
| `definitions(): list<AttributeDefinition>` | Return definitions for `sku`, `name`, and `priceAmount`. |

### `ProductAttributeValues`

Companion entity (`#[Table(extends: Product::class)]`) that stores custom attribute values.

| Method | Description |
|---|---|
| `set(string $code, mixed $value): void` | Store a value in the JSON blob. |
| `get(string $code): mixed` | Retrieve a stored value; returns `null` if not present. |
| `has(string $code): bool` | Check whether a value is stored for the given code. |
| `all(): array<string, mixed>` | Return all stored values as a `{code: value}` map. |
| `clear(string $code): void` | Remove a stored value; sets the column to `null` if no values remain. |

## Related Packages

- [markommerce/attribute](/docs/packages/attribute/) --- attribute kernel: type registry, definition service, and value validation
- [markommerce/attribute-pgsql](/docs/packages/attribute-pgsql/) --- PostgreSQL storage driver
- [markommerce/catalog](/docs/packages/catalog/) --- provides the `Product` entity
- [markommerce/catalog-attribute-scope](/docs/packages/catalog-attribute-scope/) --- adds per-scope overrides to product attribute values
