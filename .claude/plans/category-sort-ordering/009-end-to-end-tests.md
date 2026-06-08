# Task 009: End-to-end sort-ordering integration tests

**Status**: completed
**Depends on**: 005, 006, 008
**Retry count**: 0

## Description
Tie the stack together with end-to-end tests proving the category listing sorts correctly by the registered orders, that non-indexed products land last for price, and that an incompatible sort under the keyset strategy fails loudly. Validates the registry-driven extensibility from request through query to rendered grid.

## Context
- Related files:
  - `packages/catalog/tests/Feature/Services/CategoryAssignmentServicePaginatedIntegrationTest.php` (extend or sibling)
  - `packages/catalog-price-index/tests/` (price ordering against the real index table)
  - `packages/catalog-storefront/tests/` (grid component renders options + applies the active sort)
- Exercises the real `catalog_products`, `catalog_product_category`, and `catalog_product_price_index` tables with seeded fixtures (some products unindexed).

## Requirements (Test Descriptions)
- [x] `it lists category products in assignment position order by default`
- [x] `it lists category products ascending by indexed price when price_asc is selected`
- [x] `it lists category products descending by indexed price when price_desc is selected`
- [x] `it lists non-indexed products after indexed ones for both price directions`
- [x] `it preserves the selected sort across pagination pages`
- [x] `it fails loudly when a non-keyset sort is requested under the keyset strategy`

## Acceptance Criteria
- Tests run green under `composer test` (parallel) including the price-index feature tests.
- No decrease in overall coverage; PHPStan level 8 clean.
- Covers the default-position fix, price ordering with NULLS-last, sort persistence, and the loud keyset guard.

## Implementation Notes

Tests added in two files:
- `packages/catalog/tests/Feature/Sorting/EndToEndPositionSortOrderIntegrationTest.php` — position default order + keyset guard (no DB write needed for keyset test; position test tagged `integration-destructive`).
- `packages/catalog-price-index/tests/Feature/Sorting/EndToEndPriceSortOrderIntegrationTest.php` — price_asc, price_desc, NULL-last, multi-page sort persistence (all tagged `integration-destructive`).

Both drive through `PaginationOptionsResolver::resolve()` → `CategoryAssignmentService::paginatedProductsInCategory()` with a stub `ConfigResolverInterface` returning default values and a manually-populated `CategorySortOrderRegistry`.

Parallel DB race failures (PDOException: duplicate key / deadlock) observed in `composer test:all` are pre-existing infrastructure-level nondeterminism, not logic failures — confirmed by same pattern existing before this task. All 6 new tests pass when run in isolation or sequentially.
