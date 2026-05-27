# markommerce/catalog-market-category-trees

Per-market category tree assignment and resolution for Markommerce.

## Installation

```bash
composer require markommerce/catalog-market-category-trees
```

Requires `markommerce/catalog` and `markommerce/market` as peer packages.

## Quick Example

Assign a category tree to a market, then resolve the active tree for that market:

```php
<?php

declare(strict_types=1);

use Markommerce\CatalogMarketCategoryTrees\Services\CategoryTreeMarketAssignmentService;
use Markommerce\CatalogMarketCategoryTrees\Services\CategoryTreeMarketResolver;

// Assign a category tree to a market
$categoryTreeMarketAssignmentService->assignTreeToMarket(treeId: $tree->id, market: 'market:eu');

// Resolve the active tree for a market
$activeTree = $categoryTreeMarketResolver->resolveTreeForMarket('market:eu');
```

## The deleteTree Plugin Guard

Installing this package activates `CategoryTreeServiceDeletePlugin` automatically. This plugin intercepts `CategoryTreeService::deleteTree` and throws `TreeHasMarketAssignmentsException` if the tree is still assigned to one or more markets --- preventing orphaned assignments:

```php
<?php

declare(strict_types=1);

use Markommerce\CatalogMarketCategoryTrees\Exceptions\TreeHasMarketAssignmentsException;

// Attempting to delete a tree that has market assignments throws:
// TreeHasMarketAssignmentsException::forTreeId($treeId, $markets)
```

The plugin replaces the friendly guard that `catalog` provided before this package was extracted. Once `markommerce/catalog-market-category-trees` is installed, the market-assignment guard is active automatically --- no additional configuration required.

## Documentation

Full usage, API reference, and examples: [markommerce/catalog-market-category-trees](https://markommerce.dev/docs/packages/catalog-market-category-trees/)
