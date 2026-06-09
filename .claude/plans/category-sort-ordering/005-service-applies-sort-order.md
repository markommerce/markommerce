# Task 005: CategoryAssignmentService applies the sort order (catalog)

**Status**: completed
**Depends on**: 001, 004
**Retry count**: 0

## Description
Make `CategoryAssignmentService::paginatedProductsInCategory()` apply the resolved sort order: let the order add its JOINs to the query, then build the `PageRequest` from the order's `sortFields()` before handing the query to the strategy. This is where category-specific column mapping and JOINs live (they can't be assembled in the resolver). Confirms the default position sort works without hand-qualified columns.

## Context
- Related files:
  - `packages/catalog/src/Services/CategoryAssignmentService.php` — `paginatedProductsInCategory()` and `buildPageRequest()`. Currently reads `$options->pageRequest`; change to read `$options->sortOrder` + `$options->size`.
  - `packages/criteria/src/Strategy/OffsetPaginationStrategy.php` (applies `pageRequest->sort` then `id ASC`) and `KeysetPaginationStrategy.php`.
  - `packages/catalog/src/Pagination/ProductCursorValueExtractor.php` (keyset cursor extraction by column).
  - Existing test fixture `makeOffsetOptions(int $pageSize, int $page, string $sortColumn)` (line ~134) builds `ResolvedPaginationOptions` with `pageRequest:`/`new SortField($sortColumn)`. Update its signature to take a `CategorySortOrderInterface` (default: a position order) and construct the DTO with `sortOrder:` + `size:` instead of `pageRequest:`. Also update the inline `new ResolvedPaginationOptions(pageRequest: ...)` at line ~277 (the "orders products by the configured sort column" test) to construct via a test-double sort order. Both currently `use Markommerce\Criteria\...\{Sort,SortField,PageRequest}` imports at the top — drop those that become unused.
- `buildPageRequest()` (lines 169-183) currently reads `$options->pageRequest` directly for page 1 / keyset and reconstructs `PageRequest::at($options->pageRequest->size, $options->pageRequest->sort, $token)` for offset page > 1. Rewrite it to build `Sort` from `$options->sortOrder->sortFields()` and `$options->size` in BOTH branches (there is no longer a pre-built `PageRequest` to read).
- Flow:
  1. `$options->sortOrder->prepareQuery($query)` adds any JOINs (no-op for position).
  2. Build `Sort` from `$options->sortOrder->sortFields()` via `new Sort(...$options->sortOrder->sortFields())` (`Sort::__construct` is variadic `SortField ...$fields` and throws `EmptySortException` on an empty list — add the `@throws EmptySortException` tag to `paginatedProductsInCategory()`/`buildPageRequest()` and rely on the interface contract that a sort order returns at least one field). Then build a `PageRequest` (size + sort, plus the offset position token for page > 1 exactly as today).
  3. Hand to the keyset or offset strategy as today.
- Keyset safety net: if the offset/keyset selection and the order's `supportsKeyset()` disagree, the resolver (004) already guards; the service may assert defensively but the primary check is in 004.

## Requirements (Test Descriptions)
Use the catalog-native `position` order plus a test-double order (one that adds a join and/or a descending sort field) to exercise the apply path — `catalog` has no price order of its own.

- [x] `it orders products by assignment position by default without a hand-qualified column`
- [x] `it orders products using the sort fields contributed by the selected order`
- [x] `it applies joins contributed by the selected sort order before paginating`
- [x] `it preserves the id ascending tie-break for equal sort values`
- [x] `it encodes the offset position token for pages beyond the first`
- [x] `it returns the requested page size of products`

## Acceptance Criteria
- The default category listing sorts by `catalog_product_category.position` with no hand-qualified column in the calling code/tests (fixes the prior broken default).
- Offset pagination across pages still works (counts, hasNext/hasPrevious, position tokens unchanged).
- Updated integration-test fixtures construct options via a sort order; PHPStan level 8 clean.

## Implementation Notes
