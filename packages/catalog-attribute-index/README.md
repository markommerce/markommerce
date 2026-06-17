# markommerce/catalog-attribute-index

Denormalized EAV read-model for Markommerce layered-nav filtering and faceting — one already-resolved row per (product_id, attribute_code, scope_signature) in `catalog_product_attribute_index`, populated by `AttributeIndexer` and read back with a live-fallback via `IndexedAttributeReader`.

## Installation

```bash
composer require markommerce/catalog-attribute-index
```

## Quick Example

```php
<?php

declare(strict_types=1);

use Markommerce\CatalogAttributeIndex\IndexedAttributeReader;

// Read an attribute value — from the index when present, live accessor as fallback
$value = $indexedAttributeReader->resolve($product, 'color');
```

```bash
# Rebuild the attribute index (chunked, N+1-free)
php marko index:rebuild attribute

# Custom chunk size
php marko index:rebuild attribute --chunk=200
```

`AttributeIndexer` materializes filterable/facetable EAV values per served scope signature into `ProductAttributeIndexEntry` rows. Multiselect attributes produce one row per member. Column-backed (static) attributes are skipped. `IndexedAttributeReader` enumerates candidate signatures in resolution order; if no index row exists for any candidate it falls back to the live `ScopedProductAttributeAccessor`, so reads are always correct even when the index is stale or empty.

## Documentation

Full usage, API reference, and examples: [markommerce/catalog-attribute-index](https://markommerce.dev/docs/packages/catalog-attribute-index/)
