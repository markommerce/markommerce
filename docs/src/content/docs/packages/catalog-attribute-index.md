---
title: markommerce/catalog-attribute-index
description: Denormalized EAV read-model for Markommerce layered-nav filtering and faceting — one already-resolved row per product, attribute code, and scope signature.
---

Denormalized EAV read-model for product attribute values. `markommerce/catalog-attribute-index` materializes filterable and facetable attribute values into a flat `catalog_product_attribute_index` table --- one already-resolved row per `(product_id, attribute_code, scope_signature)`. The `AttributeIndexer` builds the table using `ScopedProductAttributeAccessor` over all served scope signatures; `IndexedAttributeReader` reads values back with a live-fallback so reads remain correct even when the index is stale or empty.

## Installation

```bash
composer require markommerce/catalog-attribute-index
```

The package is a `marko-module` and registers its bindings automatically via `module.php`. It requires `markommerce/catalog-attribute-scope`, `markommerce/indexer`, and their transitive dependencies.

## Usage

### Rebuilding the index from the CLI

```bash
# Full rebuild with default chunk size (500)
php marko index:rebuild attribute

# Full rebuild with a custom chunk size
php marko index:rebuild attribute --chunk=200
```

The command is provided by `markommerce/indexer` and dispatches to the `AttributeIndexer` registered under the name `attribute`.

### Reading attribute values via the index

Inject `IndexedAttributeReader` and call `resolve()`. When an index row exists for the active scope the indexed value is returned directly; otherwise the live `ScopedProductAttributeAccessor` is used as a fallback:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogAttributeIndex\IndexedAttributeReader;

class ProductAttributeView
{
    public function __construct(
        private IndexedAttributeReader $indexedAttributeReader,
    ) {}

    public function color(Product $product): mixed
    {
        // Returns the indexed value when present; falls back to live resolution
        return $this->indexedAttributeReader->resolve($product, 'color');
    }
}
```

The reader enumerates candidate scope signatures in resolution order (most-specific first, then ancestors, then the base/empty signature). The first candidate that has index rows wins. If no candidate matches, the reader delegates to `ScopedProductAttributeAccessor::resolve()`.

### What gets indexed

`AttributeIndexer` selects attribute definitions where `filterable === true` **or** `facetable === true`, restricted to `entity_type = 'product'`. Column-backed (static) attributes (`sku`, `name`, `priceAmount`) are skipped --- they live in native columns and do not need an EAV index.

For each indexed attribute:

- If the definition is `scopable` and declares `config['axes']`, the indexer calls `ServedScopesProviderInterface::signatures()` to get the set of non-default scope combinations.
- If the definition is not scopable or has no axes configured, only the base (global) row is written.
- Scoped rows are only written when the resolved scoped value differs from the base value (skip-redundant optimization).
- Multiselect attributes produce **one row per array member**; all other types produce one row.

### Rebuilding programmatically

Inject `AttributeIndexer` and use the `IndexerInterface` methods inherited from `AbstractIndexer`:

```php
<?php

declare(strict_types=1);

use Markommerce\CatalogAttributeIndex\AttributeIndexer;

class AttributeIndexManager
{
    public function __construct(
        private AttributeIndexer $attributeIndexer,
    ) {}

    public function rebuild(): int
    {
        // Full rebuild in 500-row chunks
        return $this->attributeIndexer->rebuildAll(chunkSize: 500);
    }

    public function reindexProduct(int $productId): int
    {
        return $this->attributeIndexer->reindexOne($productId);
    }

    public function reindexProducts(array $productIds): int
    {
        return $this->attributeIndexer->reindex($productIds);
    }
}
```

All methods return the total count of index rows written.

### Reading raw index rows

`ProductAttributeIndexRepository::findValues()` returns all rows for a `(product_id, attribute_code, scope_signature)` tuple. Multiselect attributes produce multiple rows; single-valued types produce 0 or 1:

```php
<?php

declare(strict_types=1);

use Markommerce\CatalogAttributeIndex\Repository\ProductAttributeIndexRepository;

class AttributeIndexLookup
{
    public function __construct(
        private ProductAttributeIndexRepository $productAttributeIndexRepository,
    ) {}

    public function lookup(int $productId, string $code, string $signature): array
    {
        // $signature is '' for the base/global row, or a ScopeSignature string
        return $this->productAttributeIndexRepository->findValues($productId, $code, $signature);
    }
}
```

## Storage Shape

Each `ProductAttributeIndexEntry` row holds:

| Column | Type | Description |
|---|---|---|
| `id` | `int` (PK, auto-increment) | Row identifier |
| `product_id` | `int` | Product entity ID |
| `attribute_code` | `string` | Attribute code (e.g. `color`, `size`) |
| `scope_signature` | `string` | Serialized scope signature; empty string `''` for the global/base row |
| `value_text` | `string\|null` | Value for `text`, `select`, `multiselect`, and `date` (ISO 8601) attributes |
| `value_number` | `decimal\|null` | Value for `int` and `decimal` attributes (stored as numeric string) |
| `value_bool` | `bool\|null` | Value for `bool` attributes |
| `value_kind` | `string` | Attribute type code (e.g. `text`, `select`, `bool`) |

Table: `catalog_product_attribute_index`.

Composite indexes:

| Index name | Columns | Purpose |
|---|---|---|
| `idx_cai_scope_code_text` | `scope_signature`, `attribute_code`, `value_text` | Filtering on text/select values |
| `idx_cai_scope_code_number` | `scope_signature`, `attribute_code`, `value_number` | Filtering on numeric values |
| `idx_cai_product_id` | `product_id` | Reindex and replace operations |

## Value Routing

| Attribute type | Column used |
|---|---|
| `text`, `select`, `multiselect`, `date` | `value_text` |
| `int`, `decimal` | `value_number` |
| `bool` | `value_bool` |

`value_kind` always stores the attribute type code so consumers can interpret the correct column without re-loading the definition.

## v1 Freshness Limitations

There is no automatic invalidation, dirty-tracking, or observer in v1. Rebuild the attribute index manually after:

- Creating, modifying, or removing an `AttributeDefinition`
- Changing attribute values on products
- Adding or removing scope axis paths (e.g. new locale or market)

## Module Bindings

`module.php` registers the following bindings and performs boot-time registration:

| Class | Notes |
|---|---|
| `AttributeIndexer` | Registered as `attribute` in `IndexerRegistry` at boot |
| `IndexedAttributeReader` | Bound as itself; inject directly |
| `ProductAttributeIndexRepository` | Bound as itself; inject directly |
| `ServedScopesProviderInterface` | Bound to `CartesianServedScopesProvider` |

## API Reference

### `IndexedAttributeReader`

| Method | Return type | Description |
|---|---|---|
| `resolve(Product $product, string $code): mixed` | `mixed` | Return the effective attribute value for the active `ScopeContext`. Walks indexed candidate signatures in resolution order; falls back to live `ScopedProductAttributeAccessor::resolve()` if no index rows exist. |

### `AttributeIndexer`

Extends `AbstractIndexer` (from `markommerce/indexer`). Implements `IndexerInterface`.

| Method | Return type | Description |
|---|---|---|
| `reindex(list<int> $ids): int` | `int` | Reindex the given product IDs. Returns total row count. |
| `reindexOne(int $id): int` | `int` | Reindex a single product. Returns row count. |
| `rebuildAll(int $chunkSize = 500): int` | `int` | Full rebuild in chunks. Returns total row count. |

### `ProductAttributeIndexRepository`

| Method | Return type | Description |
|---|---|---|
| `replaceForProducts(list<int> $productIds, list<ProductAttributeIndexEntry> $rows): void` | `void` | Delete all existing rows for the given product IDs then batch-insert new rows. |
| `findValues(int $productId, string $code, string $signature): list<ProductAttributeIndexEntry>` | `list<ProductAttributeIndexEntry>` | Return all rows for a `(product_id, attribute_code, scope_signature)` tuple. |
| `truncate(): void` | `void` | Truncate the entire index table. |

### `ProductAttributeIndexEntry`

Entity mapped to `catalog_product_attribute_index`. See Storage Shape above.

## Related Packages

- [markommerce/indexer](/docs/packages/indexer/) --- shared kernel; provides `AbstractIndexer`, `ScopePassRunner`, `IndexRepository`, and the `index:rebuild` CLI command
- [markommerce/catalog-attribute-scope](/docs/packages/catalog-attribute-scope/) --- `ScopedProductAttributeAccessor` used by `AttributeIndexer` and as the live-fallback in `IndexedAttributeReader`
- [markommerce/catalog-attribute](/docs/packages/catalog-attribute/) --- provides `ProductAttributeDefinitions` and the global `ProductAttributeAccessor`
- [markommerce/attribute](/docs/packages/attribute/) --- attribute kernel: `AttributeDefinition`, type registry, and definition repository
- [markommerce/scope](/docs/packages/scope/) --- `ScopeContext`, `ScopeSignature`, `SignatureCandidateEnumerator` used by the reader
- [markommerce/catalog-price-index](/docs/packages/catalog-price-index/) --- sibling index package for pre-resolved product prices
