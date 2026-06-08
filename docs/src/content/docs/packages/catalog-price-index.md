---
title: markommerce/catalog-price-index
description: Denormalized product price index for fast sorting and filtering — one row per product, populated by the full pricing pipeline, with per-market amounts stored in a JSONB scopes column.
---

Denormalized product price index for fast sorting and filtering. `markommerce/catalog-price-index` provides a single bulk-upsert table keyed on `product_id` with per-market scope overrides in a `scopes` JSON column via `HasScopes`. The indexer runs the full `BatchPriceResolverInterface` pipeline over chunks of products and writes results in a single SQL upsert per chunk --- N+1-free by design.

## Installation

```bash
composer require markommerce/catalog-price-index
```

The package is a `marko-module` and registers its bindings automatically via `module.php`. To also index per-market prices, install the market bridge:

```bash
composer require markommerce/catalog-price-index-market
```

## Usage

### Rebuilding the index from the CLI

```bash
# Full rebuild with default chunk size (500)
php marko catalog:price-index:rebuild

# Full rebuild with a custom chunk size
php marko catalog:price-index:rebuild --chunk=200
```

The command truncates the existing index and rebuilds it in chunks. It prints the total number of upserted entries when complete.

### Rebuilding programmatically

Inject `PriceIndexerInterface` and call one of its three methods:

```php
<?php

declare(strict_types=1);

use Markommerce\CatalogPriceIndex\Contracts\PriceIndexerInterface;

// Rebuild the full index (truncates first, then chunks)
$count = $priceIndexer->rebuildAll(chunkSize: 500);

// Reindex a single product
$count = $priceIndexer->reindexProduct(productId: 42);

// Reindex a specific set of products
$count = $priceIndexer->reindexProducts(ids: [1, 2, 3]);
```

All three methods return an `int` count of upserted index entries.

### Reading an index entry

Use `ProductPriceIndexRepositoryInterface` to look up a single entry by product ID:

```php
<?php

declare(strict_types=1);

use Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface;

$entry = $productPriceIndexRepository->findByProductId(42); // ?ProductPriceIndexEntry

if ($entry !== null) {
    echo $entry->amount;       // base decimal amount, e.g. "29.9900"
    echo $entry->currencyCode; // e.g. "USD"
}
```

### Reading multiple index entries in one query

Use `findByProductIds()` to batch-load index entries for an entire page of products --- one query regardless of page size:

```php
<?php

declare(strict_types=1);

use Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface;

// $productIds is a list<int> of product IDs on the current page
$entries = $productPriceIndexRepository->findByProductIds($productIds);
// Returns array<int, ProductPriceIndexEntry>, keyed by productId

foreach ($productIds as $id) {
    $entry = $entries[$id] ?? null; // null when not yet indexed
    if ($entry !== null) {
        echo $entry->amount;
    }
}
```

Products absent from the returned map have not yet been indexed; fall back to `PriceResolverInterface` for those.

### Reading a market-scoped amount

When `markommerce/catalog-price-index-market` is installed, the indexer writes per-market amounts into the `scopes` column. Read them back via `ScopeResolver` under an active market context:

```php
<?php

declare(strict_types=1);

use Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Resolver\ScopeResolver;

$entry = $productPriceIndexRepository->findByProductId(42);

$scopeContext->in('market', 'us');
$amount = $scopeResolver->resolved($entry, 'amount'); // market-scoped or base fallback
```

### Implementing a market-aware index

By default, `IndexedMarketsProviderInterface` returns an empty list, so only the base price is indexed. Install `markommerce/catalog-price-index-market` to register per-market amounts automatically. The bridge overrides `IndexedMarketsProviderInterface` via `#[Preference]` to return all configured markets (excluding the default, which is already covered by the base pass).

## Storage Shape

Each `ProductPriceIndexEntry` row holds:

| Column | Description |
|---|---|
| `product_id` | Unique product identifier (unique index key) |
| `amount` | Base resolved price (default market / no market) as `decimal(20,4)` |
| `currency_code` | Three-letter ISO 4217 code |
| `scopes` | JSON map of per-market overrides written by the indexer |

Table: `catalog_product_price_index`.

When `markommerce/catalog-price-index-market` is installed, the indexer writes per-market amounts into `scopes` in the format `{"market:us":{"amount":"12.3400"}}`. Reading them back requires `ScopeResolver` under an active market context.

## N+1-Free Contract

The indexer calls `BatchPriceResolverInterface::resolve()` once per market pass over a chunk. Any `PriceContributorInterface` implementation **must** load its data set-wise --- a single query for the whole batch, not one query per product. See [markommerce/catalog](/docs/packages/catalog/) for documentation on implementing `PriceContributorInterface`.

The indexer itself is N+1-free by design: it loads a chunk of products with a single `whereIn` query, runs the batch pipeline, and writes results in a single bulk upsert.

## Sort Orders

When `markommerce/catalog-price-index` is installed, its `module.php` boot registers two sort orders into the shared `CategorySortOrderRegistry`:

| Key | Class | Label | Description |
|---|---|---|---|
| `price_asc` | `AscendingIndexedPriceSortOrder` | Price: Low to High | Orders by `catalog_product_price_index.amount` ascending; products not in the index sort last |
| `price_desc` | `DescendingIndexedPriceSortOrder` | Price: High to Low | Orders by `catalog_product_price_index.amount` descending; products not in the index sort last |

Both orders LEFT JOIN `catalog_product_price_index` on the category product query, set `supportsKeyset()` to `false` (the JOIN makes cursor-based pagination unreliable), and place un-indexed products last in both directions via a `NullsPlacement::Last` sort field.

When `markommerce/catalog-price-index-market` is installed, its `#[Preference]` overrides replace `AscendingIndexedPriceSortOrder` and `DescendingIndexedPriceSortOrder` with market-aware variants (`ScopedAscendingIndexedPriceSortOrder` / `ScopedDescendingIndexedPriceSortOrder`) that use a `COALESCE` expression to fall back from the active market's JSON override amount to the base amount.

## v1 Freshness Limitations

There is no automatic invalidation, dirty-tracking, or observer in v1. Rebuild the index manually after:

- Changing a product's base price
- Changing a per-market price override
- Adding or removing a market
- Installing or changing a `PriceContributorInterface` that affects amounts

## Module Bindings

`module.php` registers the following default bindings:

| Interface | Default Implementation |
|---|---|
| `ProductPriceIndexRepositoryInterface` | `ProductPriceIndexRepository` |
| `IndexedMarketsProviderInterface` | `DefaultIndexedMarketsProvider` |
| `PriceIndexerInterface` | `PriceIndexer` |

## API Reference

### `PriceIndexerInterface`

| Method | Return type | Description |
|---|---|---|
| `reindexProduct(int $id)` | `int` | Reindex a single product. Returns `1` on success, `0` if the product has no resolvable price. |
| `reindexProducts(array $ids)` | `int` | Reindex a list of product IDs. Returns the count of upserted entries. |
| `rebuildAll(int $chunkSize = 500)` | `int` | Truncate the index and rebuild it for all products in chunks. Returns the total count of upserted entries. |

### `ProductPriceIndexRepositoryInterface`

| Method | Return type | Description |
|---|---|---|
| `upsertMany(array $entries)` | `void` | Bulk-upsert index entries keyed on `product_id` in a single SQL statement. |
| `findByProductId(int $productId)` | `?ProductPriceIndexEntry` | Look up the index entry for a specific product. Returns `null` when no entry exists. |
| `findByProductIds(array $productIds)` | `array<int, ProductPriceIndexEntry>` | Look up index entries for a set of product IDs in a single query. Returns a map keyed by `productId`; missing entries are absent from the map. |
| `truncate()` | `void` | Delete all rows from the index table. |

### `IndexedMarketsProviderInterface`

| Method | Return type | Description |
|---|---|---|
| `markets()` | `list<string>` | Return the list of market scope keys to index per-market amounts for. Default implementation returns `[]`. |

### `ProductPriceIndexEntry`

Table: `catalog_product_price_index`. Implements `HasScopesInterface`.

| Property | Type | Column | Notes |
|---|---|---|---|
| `$id` | `?int` | `id` | Primary key, auto-increment |
| `$productId` | `int` | `product_id` (unique) | Unique product identifier |
| `$amount` | `?string` | `amount` (`decimal(20,4)`, nullable) | Base resolved price as a decimal string |
| `$currencyCode` | `string` | `currency_code` (length 3) | ISO 4217 currency code |
| `$scopes` | JSON | `scopes` | Per-market amount overrides; managed via `HasScopes` |

### CLI Command

| Command | Option | Description |
|---|---|---|
| `catalog:price-index:rebuild` | `--chunk=N` | Rebuild the full product price index. Defaults to chunk size `500`. |

## markommerce/catalog-price-index-market

`markommerce/catalog-price-index-market` is a thin bridge package that makes the price index market-aware. It does two things at boot:

1. Registers `ProductPriceIndexEntry.amount` on the `market` axis via `ScopedFieldRegistry`, enabling `ScopeResolver::resolved($entry, 'amount')` to return the market-specific amount (or fall back to the base `amount`).
2. Overrides `IndexedMarketsProviderInterface` via `#[Preference]` with `ScopedIndexedMarketsProvider`, which returns all configured market paths excluding the default. The base pass in `PriceIndexer` already covers the default market; this bridge tells the indexer which additional passes to run.

Installing this bridge is what makes the price index market-aware. Without it, `PriceIndexer` only writes a single base `amount` per product.

### Installation

```bash
composer require markommerce/catalog-price-index-market
```

### `ScopedIndexedMarketsProvider`

Replaces (via `#[Preference]`) `DefaultIndexedMarketsProvider`. Implements `IndexedMarketsProviderInterface`.

| Method | Return type | Description |
|---|---|---|
| `markets()` | `list<string>` | Return all market paths from `ScopeRegistryInterface` excluding the axis default. Returns `[]` when the `market` axis is not configured. |

## Related Packages

- [markommerce/catalog](/docs/packages/catalog/) --- Provides `BatchPriceResolverInterface` and `PriceContributorInterface`; the pricing pipeline that populates the index
- [markommerce/catalog-market](/docs/packages/catalog-market/) --- Registers `Product.priceAmount` on the `market` axis; required by `markommerce/catalog-price-index-market`
- [markommerce/scope](/docs/packages/scope/) --- `HasScopes`, `ScopeResolver`, and `ScopedFieldRegistry` used to store and read per-market amounts
- [markommerce/market](/docs/packages/market/) --- Declares the `market` scope axis; required by `markommerce/catalog-price-index-market`
- [markommerce/currency](/docs/packages/currency/) --- `CurrencyResolver` used to determine the currency for each index entry
- [markommerce/money](/docs/packages/money/) --- `Money` value object produced by the pricing pipeline
