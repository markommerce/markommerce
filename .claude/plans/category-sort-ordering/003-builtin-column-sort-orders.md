# Task 003: Position sort order + ColumnSortOrder + module wiring (catalog)

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Provide a reusable `ColumnSortOrder` implementation of `CategorySortOrderInterface` (the developer-facing convenience for registering a plain-column order by) and register the only built-in catalog order for v1 — `position` (the default) — in `catalog`'s `module.php`. Name/SKU orders are intentionally out of scope for now but become one-line registrations via `ColumnSortOrder` later.

## Context
- Related files:
  - New: `packages/catalog/src/Sorting/ColumnSortOrder.php`
  - `packages/catalog/module.php` (add `CategorySortOrderRegistry` to `singletons`; register `position` in `boot`, mirroring how `PriceContributorRegistry` + `BasePriceContributor` are wired)
  - `packages/criteria/src/Sort/SortField.php`, `SortDirection.php`
- The only built-in to register in v1:
  - `position` → `SortField('catalog_product_category.position', ASC)`, no JOIN (`prepareQuery` no-op — the assignment JOIN is intrinsic to the category query), `supportsKeyset() = false` (the column lives on the join table, not the `Product` entity, so `ProductCursorValueExtractor` cannot read it).
- `ColumnSortOrder` is constructed with key, label, column, direction, and `supportsKeyset` flag (and an optional NULLS placement so other packages can reuse it). `prepareQuery` is a no-op (plain columns add no joins).

## Requirements (Test Descriptions)
- [x] `it exposes its configured key and label`
- [x] `it returns a single sort field for its configured column and direction`
- [x] `it reports its configured keyset support`
- [x] `it adds no joins to the query for a plain column order`
- [x] `it registers the position order in the catalog module boot`
- [x] `it registers position as a non-keyset order`

## Acceptance Criteria
- `ColumnSortOrder` lives in `Markommerce\Catalog\Sorting`, no `final`, constructor injection only.
- Only `position` is registered in v1, via the `module.php` `boot` closure against the singleton `CategorySortOrderRegistry`.
- `position` is a single ascending key (no direction variants needed for v1).
- PHPStan level 8 clean.

## Implementation Notes
- `ColumnSortOrder` created in `packages/catalog/src/Sorting/ColumnSortOrder.php` — plain constructor injection, no `final`, implements `CategorySortOrderInterface`, `prepareQuery` is intentional no-op.
- `module.php` updated: `CategorySortOrderRegistry` added to `singletons`; `boot` closure extended with a third parameter `CategorySortOrderRegistry $categorySortOrderRegistry` and registers the `position` order.
- Boot closure in `PriceResolverTest.php` (Feature) was calling `$catalogModule['boot'](...)` positionally with 2 args — updated to `$container->call($catalogModule['boot'])` to support the new 3-arg signature gracefully.
- PHPStan level 8 clean on all new/modified source files. Pre-existing errors in other catalog src files were not introduced by this task.
