# markommerce/catalog-market

Market integration for catalog entities --- per-market category tree assignment and resolution, a deletion guard plugin, and a `ScopedFieldRegistry` boot hook for future market-scoped fields.

## Installation

```bash
composer require markommerce/catalog-market
```

## Quick Example

```php
use Markommerce\CatalogMarket\Services\CategoryTreeMarketAssignmentService;
use Markommerce\CatalogMarket\Services\CategoryTreeMarketResolver;

// Assign a tree to a market
$categoryTreeMarketAssignmentService->assignTreeToMarket($euTree->id, 'market:eu-de');

// Resolve the active tree for a market (falls back to the default tree)
$tree = $categoryTreeMarketResolver->resolveTreeForMarket('market:eu-de');
```

## Documentation

Full usage, API reference, and examples: [markommerce/catalog-market](https://markommerce.dev/docs/packages/catalog-market/)
