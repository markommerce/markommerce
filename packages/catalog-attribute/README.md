# markommerce/catalog-attribute

Binds the `markommerce/attribute` kernel to `Product` — adds custom attribute values to products (global scope only).

## Installation

```bash
composer require markommerce/catalog-attribute
```

## Quick Example

The package auto-wires itself at boot. `ProductAttributeAccessor` implements `AttributeValueAccessorInterface` and handles both JSON-stored custom attributes and native column-backed static attributes:

```php
use Markommerce\CatalogAttribute\ProductAttributeAccessor;

// set a custom attribute value (validated and cast)
$accessor->set($product, 'color', 'red');

// read it back (falls back to definition default if not stored)
$color = $accessor->get($product, 'color');

// read all attributes — statics (sku, name, priceAmount) plus stored custom values
$all = $accessor->all($product);

// remove a custom attribute value (no-op for Column-backed statics)
$accessor->clear($product, 'color');
```

Values are persisted when the product is saved via `ProductRepository->save($product)`.

### Companion entity

`ProductAttributeValues` (`#[Table(extends: Product)]`) adds an `attribute_values` JSON column to the `catalog_products` table (same row). It holds a flat `{code: value}` map and is auto-linked as a `Product` extender at boot.

### Static attributes

The following native `Product` columns are exposed as `Column`-backed attribute definitions and always appear in `all()`:

| Code | Type | Backed by |
|---|---|---|
| `sku` | `text` | `Product::$sku` |
| `name` | `text` | `Product::$name` |
| `priceAmount` | `decimal` | `Product::$priceAmount` |

Static definitions take precedence over any DB-stored definition with the same code.

### Entity registration

The module registers `product → Product::class` in `AttributeEntityClassMap` so the reserved-code check in `markommerce/attribute` rejects custom codes that collide with native product columns.

## Documentation

Full usage, API reference, and examples: [markommerce/catalog-attribute](https://markommerce.dev/docs/packages/catalog-attribute/)
