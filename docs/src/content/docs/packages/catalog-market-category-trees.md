---
title: markommerce/catalog-market-category-trees
description: Per-market category tree assignment and resolution for Markommerce catalog --- assigns trees to markets and resolves the active tree via a Plugin guard on tree deletion.
---

Per-market category tree assignment and resolution for Markommerce catalog. `markommerce/catalog-market-category-trees` extracts the market-specific category tree concerns from `markommerce/catalog` into a standalone package. It provides `CategoryTreeMarketAssignmentService` to assign and unassign trees to markets, `CategoryTreeMarketResolver` to resolve the active tree for a given market (with automatic fallback to the default tree), and `CategoryTreeServiceDeletePlugin` to guard against deleting a tree that still has market assignments.

## Installation

```bash
composer require markommerce/catalog-market-category-trees
```

This package requires `markommerce/catalog` (for `CategoryTree` entities and `CategoryTreeRepositoryInterface`) and `markommerce/market` (for the `market` axis declaration). Both are pulled in automatically as Composer dependencies.

## Usage

### Assigning a tree to a market

Use `CategoryTreeMarketAssignmentService` to assign a specific tree to a market or remove the explicit assignment:

```php
<?php

declare(strict_types=1);

use Markommerce\CatalogMarketCategoryTrees\Services\CategoryTreeMarketAssignmentService;
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

use Markommerce\CatalogMarketCategoryTrees\Services\CategoryTreeMarketResolver;
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
use Markommerce\CatalogMarketCategoryTrees\Exceptions\TreeHasMarketAssignmentsException;

try {
    $categoryTreeService->deleteTree($euTree->id);
} catch (TreeHasMarketAssignmentsException $e) {
    // Tree still assigned to markets — unassign them first
}
```

The plugin is declared with `#[Plugin(target: CategoryTreeServiceInterface::class)]` and wired automatically when the module is loaded. No manual wiring is needed.

## Tier 3 Wiring Diagram

```
markommerce/catalog
  CategoryTreeServiceInterface  ←── #[Plugin(Before: deleteTree)]  CategoryTreeServiceDeletePlugin
  CategoryTreeRepositoryInterface ←── used by CategoryTreeMarketResolver

markommerce/catalog-market-category-trees
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
- [markommerce/market](/docs/packages/market/) --- Declares the `market` axis used as the market identifier convention
- [markommerce/catalog-market](/docs/packages/catalog-market/) --- Companion placeholder bridge for future market-scoped catalog fields
