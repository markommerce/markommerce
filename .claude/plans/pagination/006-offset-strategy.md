# Task 006: OffsetPaginationStrategy + random-access page

**Status**: complete
**Depends on**: 002, 003, 004, 005
**Retry count**: 0

## Description
Implement `OffsetPaginationStrategy` (classic `LIMIT/OFFSET`) and its result `OffsetPage`, which extends `Page` AND implements `RandomAccessPageInterface`. It applies the sort + computed offset, fetches `size + 1` rows to detect `hasNext` without a count, and uses an injected `RowCounterInterface` to derive totals/page numbers.

## Context
- Files: `packages/criteria/src/Strategy/OffsetPaginationStrategy.php`, `packages/criteria/src/Strategy/OffsetPage.php` (namespace `Markommerce\Criteria\Strategy`).
- Implements `paginate()` with the signature fixed in Task 003 (recommended: `paginate(RepositoryQueryBuilder $query, PageRequest $pageRequest, ?CursorValueExtractor $cursorExtractor = null): Page`) — offset IGNORES the extractor param.
- Decode `PageRequest.position` via `PositionCodec`; if it is a keyset token, throw `IncompatiblePositionException`. Null position = page 1.
- Apply each `SortField` via `RepositoryQueryBuilder::orderBy(column, direction)`; always append a deterministic `id ASC` tie-break.
- Fetch `size + 1` to compute `hasNext`; trim the extra row from the returned `EntityCollection`. NOTE: `paginate()` receives one `RepositoryQueryBuilder`; since the builder is stateful and `orderBy`/`limit`/`offset` mutate it in place, do the COUNT for totals BEFORE applying `limit`/`offset`, or accept a separate count source. See next bullet.
- `RowCounterInterface` (injected; use a fake in tests) gives `totalItems`; derive `totalPages = ceil(total / size)`. `positionForPage(int)` encodes an offset token; throw `PageOutOfRangeException` for pages < 1 or > totalPages.
- **COUNT correctness with JOINs:** `RepositoryQueryBuilder::count()` delegates to a query builder whose aggregate path (`runAggregate`) emits `SELECT COUNT(*) FROM <table>` + WHERE only and **drops JOIN clauses**. Do NOT assume `count()` on a joined builder is correct. The offset strategy must take its total from the `RowCounterInterface` it is given, and the caller (catalog, Task 012) is responsible for handing it a counter whose COUNT is join-safe for the actual result set. Keep the strategy decoupled: it asks the counter for a number; it does not build the count query itself. The unit tests here use a fake counter returning a fixed total, so this constraint is satisfied at the seam — but the task description and the `RowCounterInterface` doc must state that the counter, not the strategy, owns count correctness.
- Build `nextPosition`/`previousPosition` as offset tokens for the adjacent pages (null at the ends).

## Requirements (Test Descriptions)
- [x] `it returns the requested number of items for a full page`
- [x] `it applies the sort fields and a deterministic id tie-break to the query`
- [x] `it computes the offset from the requested page and size`
- [x] `it reports hasNext by fetching one extra row`
- [x] `it derives current page total pages and total items from the counter`
- [x] `it encodes an offset position for an arbitrary page via positionForPage`
- [x] `it throws PageOutOfRangeException for a page beyond the last`
- [x] `it throws IncompatiblePositionException when given a keyset token`

## Acceptance Criteria
- `OffsetPage` is both a `Page` and a `RandomAccessPageInterface`.
- All requirements have passing tests against a fake query builder + fake counter.
- No decrease in coverage.

## Implementation Notes
- `OffsetPage` is a `readonly class` extending `Page` and implementing `RandomAccessPageInterface`; holds `PositionCodec` for on-demand `positionForPage` encoding.
- `OffsetPaginationStrategy` calls `rowCounter->count($query)` BEFORE `orderBy`/`offset`/`limit` to get the unaffected total.
- `getEntities()` on the fake builder returns `EntityCollection<Entity>` (PHPStan-annotated) to keep static analysis clean.
- All 15 remaining PHPStan errors are pre-existing in `KeysetPaginationStrategy.php` and `KeysetPaginationStrategyTest.php`; my new files produce zero new errors.
