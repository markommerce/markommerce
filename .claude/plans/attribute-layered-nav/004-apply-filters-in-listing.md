# Task 004: Apply active filters in the catalog listing query

**Status**: completed
**Depends on**: 003
**Retry count**: 0

## Description
Wire the `ProductListFilterRegistry` into the category product listing: `paginatedProductsInCategory`
accepts a `FilterSelection` and applies all registered filter contributors to the query BEFORE
pagination (alongside the existing sort-order `prepareQuery`).

## Context
- File: `packages/catalog/src/Services/CategoryAssignmentService.php`,
  `paginatedProductsInCategory(int $categoryId, ResolvedPaginationOptions $options)`. STUDY how it
  builds the query + calls `$options->sortOrder->prepareQuery($query)` before paginating.
- Inject `ProductListFilterRegistry` (task 003) into the service.
- Add a `FilterSelection $filters = new FilterSelection([])` parameter (default empty so existing
  callers/tests keep working). CONFIRMED backward-compatible: every current caller passes exactly 2
  positional args — `ProductGridComponent::data()` (line ~65), `CategoryController::isViewAllActive()`
  (line ~176), and the Feature tests `CategoryAssignmentServicePaginatedIntegrationTest` (5 calls),
  `EndToEndPositionSortOrderIntegrationTest`, `EndToEndPriceSortOrderIntegrationTest`,
  `ScopedIndexedPriceSortOrderIntegrationTest`, and `ProductGridComponentTest` /
  `ScopedProductGridComponentTest`. A defaulted 3rd param leaves all of them compiling + passing
  unchanged. Note the default value `new FilterSelection([])` must be a valid constant-expression default
  (a `new` in a default is allowed in PHP 8.1+); confirm `FilterSelection`'s constructor accepts an empty
  array with no other required args, otherwise use `?FilterSelection $filters = null` + null-coalesce inside.
- Inject `ProductListFilterRegistry` (task 003) into the service constructor — this is a new REQUIRED
  constructor dependency, so update the `catalog/module.php` binding for `CategoryAssignmentService` (or
  its autowiring) and any test that constructs the service directly (the integration tests resolve it
  from the container, so they get the new dep automatically; check for any manual `new
  CategoryAssignmentService(...)`).
- After building the base category query and BEFORE pagination, iterate `$registry->all()` and call
  `$filter->apply($query, $filters)`. Order vs `sortOrder->prepareQuery`: apply filters first (or after —
  document; both add to the same builder). The EXISTS filter (task 006) uses `whereRaw`, which composes
  cleanly with the existing `where`/`join` and the sort order's `leftJoin`/`orderBy`.
- Keep behaviour identical when `$filters` is empty (no contributors match → no-op).
- Update existing `CategoryAssignmentService` unit/integration tests for the new param (default empty);
  no behaviour change for the no-filter path.

## Requirements (Test Descriptions)
- [x] `it applies registered filter contributors to the category query before pagination`
- [x] `it leaves the query unchanged when the filter selection is empty`
- [x] `it still paginates and sorts as before when no filters are selected`
- [x] `it narrows the result set when a filter contributor adds a constraint`

## Acceptance Criteria
- Listing applies all registered filters before pagination; empty selection is a no-op (existing behaviour preserved).
- `catalog` depends only on its own filter registry (no attribute coupling).

## Implementation Notes
- Added `ProductListFilterRegistry $filterRegistry = new ProductListFilterRegistry()` as a defaulted 6th constructor parameter to `CategoryAssignmentService`. The default means existing callers that pass 5 positional args continue to work unchanged.
- Added `FilterSelection $filters = new FilterSelection()` as a defaulted 3rd parameter to `paginatedProductsInCategory`. Filters are applied via `foreach ($this->filterRegistry->all() as $filter) { $filter->apply($query, $filters); }` BEFORE `$options->sortOrder->prepareQuery($query)`.
- Added `ProductListFilterRegistry::class` as a singleton in `catalog/module.php` so the container shares one instance for registration and resolution.
- Updated all 3 anonymous subclass overrides of `paginatedProductsInCategory` in test files (`CategoryLayoutTest.php`, `ScopedProductGridComponentTest.php`, `ProductGridComponentTest.php`) to include the `FilterSelection` parameter in their override signature (PHP requires compatible method signatures in inheritance).
- All 4 requirements implemented as integration tests (group `integration-destructive`) since `paginatedProductsInCategory` requires a real DB query builder.
- Pre-existing failure in `RelocationTest.php` (missing `Filtering` in allowed dirs list) was already present before task 004 — it was introduced by task 003.
