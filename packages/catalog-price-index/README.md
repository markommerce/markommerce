# markommerce/catalog-price-index

Denormalized product price index for fast sorting and filtering --- one row per product, populated by the full pricing pipeline, with per-market amounts stored in the `scopes` JSON column via `HasScopes`.

## Installation

```bash
composer require markommerce/catalog-price-index
```

## Quick Example

```php
<?php

declare(strict_types=1);

use Markommerce\CatalogPriceIndex\Contracts\PriceIndexerInterface;

// Rebuild the full index (chunked, N+1-free)
$count = $priceIndexer->rebuildAll(chunkSize: 500);

// Rebuild a specific product
$priceIndexer->reindexProduct(productId: 42);

// Rebuild a batch of products
$priceIndexer->reindexProducts(ids: [1, 2, 3]);
```

```bash
# Rebuild from the CLI
php marko catalog:price-index:rebuild
php marko catalog:price-index:rebuild --chunk=200
```

## Documentation

Full usage, API reference, and examples: [markommerce/catalog-price-index](https://markommerce.dev/docs/packages/catalog-price-index/)
