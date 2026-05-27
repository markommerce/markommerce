---
title: markommerce/catalog-market
description: Market integration for catalog entities — category tree assignment per market, active-tree resolution, a deletion guard plugin, and a ScopedFieldRegistry hook for future market-scoped fields.
---

Market integration for catalog entities. `markommerce/catalog-market` is the single Tier 3 package a merchant installs to get full market behaviour for the catalog: per-market category tree assignment and resolution, a `#[Before]` plugin that guards against deleting trees still serving a market, and a **placeholder** `ScopedFieldRegistry` boot closure that reserves the hook for future market-scoped fields (price, visibility) once those columns land on `Product`.

## Installation

```bash
composer require markommerce/catalog-market
```

This package requires `markommerce/catalog` (for `CategoryTree` entities and `CategoryTreeRepositoryInterface`), `markommerce/catalog-scope` (storage layer), and `markommerce/market` (axis declaration). All are pulled in automatically as Composer dependencies.

## Usage

### Assigning a tree to a market

Use `CategoryTreeMarketAssignmentService` to assign a specific tree to a market or remove the explicit assignment:

```php
<?php

declare(strict_types=1);

use Markommerce\CatalogMarket\Services\CategoryTreeMarketAssignmentService;
use Markommerce\Catalog\Exceptions\CategoryTreeNotFoundException;

// Assign the EU tree to the eu-de market
$categoryTreeMarketAssignmentService->assignTreeToMarket($euTree->id, 'market:eu-de');

// Remove the explicit assignment (market falls back to the default tree)
$categoryTreeMarketAssignmentService->unassignMarket('market:eu-de');
```

`assignTreeToMarket()` throws `CategoryTreeNotFoundException` when the tree does not exist.

### Resolving the active tree for a market

Use `CategoryTreeMarketResolver` to find which tree is active for a given market:

```php
<?php

declare(strict_types=1);

use Markommerce\CatalogMarket\Services\CategoryTreeMarketResolver;
use Markommerce\Catalog\Exceptions\CategoryTreeNotFoundException;
use Markommerce\Catalog\Exceptions\DefaultTreeMissingException;

try {
    $tree = $categoryTreeMarketResolver->resolveTreeForMarket('market:eu-de');
} catch (CategoryTreeNotFoundException $e) {
    // The explicitly assigned tree no longer exists
} catch (DefaultTreeMissingException $e) {
    // No explicit assignment and no default tree is configured
}
```

The resolver looks up the explicit assignment first. If none exists, it falls back to the default tree via `CategoryTreeRepositoryInterface::findDefault()`.

### Deletion guard via plugin

`CategoryTreeServiceDeletePlugin` intercepts `CategoryTreeServiceInterface::deleteTree()` via a `#[Before]` plugin and throws `TreeHasMarketAssignmentsException` when the tree being deleted still has market assignments:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Contracts\CategoryTreeServiceInterface;
use Markommerce\CatalogMarket\Exceptions\TreeHasMarketAssignmentsException;

try {
    $categoryTreeService->deleteTree($euTree->id);
} catch (TreeHasMarketAssignmentsException $e) {
    // Tree still assigned to markets — unassign them first
}
```

The plugin is declared with `#[Plugin(target: CategoryTreeServiceInterface::class)]` and wired automatically when the module is loaded. No manual wiring is needed.

## Placeholder Status

The `boot` closure in `module.php` is type-hinted on `ScopedFieldRegistry` but does not register any fields yet:

```php title="packages/catalog-market/module.php"
<?php

declare(strict_types=1);

use Markommerce\CatalogMarket\Contracts\CategoryTreeMarketAssignmentRepositoryInterface;
use Markommerce\CatalogMarket\Repositories\CategoryTreeMarketAssignmentRepository;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;

return [
    'require' => [
        'markommerce/catalog-scope' => '*',
        'markommerce/market'        => '*',
        'markommerce/catalog'       => '*',
    ],
    'bindings' => [
        CategoryTreeMarketAssignmentRepositoryInterface::class => CategoryTreeMarketAssignmentRepository::class,
    ],
    'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
        // Placeholder: no scoped fields registered yet.
    },
];
```

The planned fields are `price` and `visibility` on `Product`. Once those columns are added to the `catalog_products` table, this bridge will register them as market-scoped via `ScopedFieldRegistry`.

## Tier 3 Wiring Diagram

```
markommerce/catalog
  CategoryTreeServiceInterface  ←── #[Plugin(Before: deleteTree)]  CategoryTreeServiceDeletePlugin
  CategoryTreeRepositoryInterface ←── used by CategoryTreeMarketResolver

markommerce/catalog-market
  CategoryTreeMarketAssignmentService  ──► CategoryTreeMarketAssignmentRepositoryInterface
  CategoryTreeMarketResolver           ──► CategoryTreeMarketAssignmentRepositoryInterface
                                       ──► CategoryTreeRepositoryInterface (catalog)
  CategoryTreeServiceDeletePlugin      ──► CategoryTreeMarketAssignmentRepositoryInterface

markommerce/market
  config/scope.php  ──► market axis available in ScopeContext
```

## API Reference

### Services

#### `CategoryTreeMarketAssignmentService`

| Method | Return type | Throws | Description |
|--------|-------------|--------|-------------|
| `assignTreeToMarket(int $treeId, string $market)` | `void` | `CategoryTreeNotFoundException` | Assign a specific tree to a market. Overwrites any existing assignment. |
| `unassignMarket(string $market)` | `void` | --- | Remove a market's explicit tree assignment. No-op if the market has no assignment. |

#### `CategoryTreeMarketResolver`

| Method | Return type | Throws | Description |
|--------|-------------|--------|-------------|
| `resolveTreeForMarket(string $market)` | `CategoryTree` | `CategoryTreeNotFoundException`, `DefaultTreeMissingException` | Return the tree assigned to a market, or the default tree when no explicit assignment exists. |

### Plugin

#### `CategoryTreeServiceDeletePlugin`

Target: `CategoryTreeServiceInterface::deleteTree(int $treeId)`

Intercept: `#[Before(method: 'deleteTree')]`

Throws `TreeHasMarketAssignmentsException` when the tree has one or more active market assignments. The exception's `context` includes the market identifiers that must be unassigned before deletion.

### Repository Interface

#### `CategoryTreeMarketAssignmentRepositoryInterface`

Extends `RepositoryInterface<CategoryTreeMarketAssignment>`.

| Method | Return type | Description |
|--------|-------------|-------------|
| `findByMarket(string $market)` | `?CategoryTreeMarketAssignment` | Return the assignment for a given market. Returns `null` when no explicit assignment exists. |
| `findByTree(int $treeId)` | `list<CategoryTreeMarketAssignment>` | Return all market assignments for a given tree. |

### Entity

#### `CategoryTreeMarketAssignment`

Table: `catalog_category_tree_market_assignments`

| Property | Type | Column | Notes |
|----------|------|--------|-------|
| `$market` | `string` | `market` (PK, length 64) | Market identifier; primary key |
| `$treeId` | `?int` | `tree_id` | FK → `catalog_category_trees`, RESTRICT on delete |

### Exceptions

| Exception | Named constructor | When thrown |
|-----------|------------------|-------------|
| `TreeHasMarketAssignmentsException` | `forTreeId(int $treeId, array $markets)` | `CategoryTreeServiceDeletePlugin::beforeDeleteTree()` finds active market assignments for the tree |

## Related Packages

- [markommerce/catalog](/docs/packages/catalog/) --- Provides `CategoryTree`, `CategoryTreeServiceInterface`, and `CategoryTreeRepositoryInterface`
- [markommerce/catalog-scope](/docs/packages/catalog-scope/) --- Provides the `scopes` column on catalog entities; required by this package
- [markommerce/market](/docs/packages/market/) --- Declares the `market` axis; required by this package
- [markommerce/scope](/docs/packages/scope/) --- `ScopedFieldRegistry` and resolution engine
