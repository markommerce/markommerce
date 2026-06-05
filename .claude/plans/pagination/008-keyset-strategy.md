# Task 008: KeysetPaginationStrategy + keyset page

**Status**: complete
**Depends on**: 002, 003, 004, 005
**Retry count**: 0

## Description
Implement `KeysetPaginationStrategy` (seek method) and its `KeysetPage` (a plain `Page`, NOT random-access). It applies a `WHERE (sortKeys, id) > anchor` predicate, orders deterministically, fetches `size + 1`, and encodes the next/previous anchors from the boundary entity's property values via a caller-supplied value extractor.

## Context
- Files: `packages/criteria/src/Strategy/KeysetPaginationStrategy.php`, `packages/criteria/src/Strategy/KeysetPage.php` (namespace `Markommerce\Criteria\Strategy`), plus a small `CursorValueExtractor` contract (closure or interface) the caller provides to read sort-key values off a hydrated entity.
- **Extractor handoff must match the `PaginationStrategyInterface::paginate()` signature decided in Task 003.** Per the recommended option there, `paginate(RepositoryQueryBuilder $query, PageRequest $pageRequest, ?CursorValueExtractor $cursorExtractor = null): Page`: offset ignores the extractor; keyset uses it. When keyset must encode a next/previous anchor but `cursorExtractor` is null, throw a loud exception (do not silently return broken tokens). Keep `KeysetPaginationStrategy` container-constructible with no per-call constructor args so Task 009's default binding resolves.
- Rationale: `RepositoryQueryBuilder` cannot return raw rows AND hydrated entities together, so keyset reads anchor values from the hydrated boundary entity's public properties through the extractor. Document that keyset sort keys must be entity-addressable.
- First page (null position) applies no seek predicate. Subsequent pages decode the keyset token (`PositionCodec`); a non-keyset token throws `IncompatiblePositionException`.
- **Seek predicate construction:** `RepositoryQueryBuilder` has NO row-value/tuple comparison helper (`where()` is single-column `(column, operator, value)`). A multi-column seek `(a, b, id) > (?, ?, ?)` must therefore be expressed either via `whereRaw('(a, b, id) > (?, ?, ?)', [...])` (pgsql supports row-value comparison) or as the expanded OR-of-AND lexicographic form using nested `where`/`orWhere`. Pick `whereRaw` for clarity; note that `whereRaw` requires column identifiers be safe (sort keys are whitelisted by catalog, so this is acceptable, but document the trust boundary). Verify the chosen form against `marko/database` `PgSqlQueryBuilder::whereRaw`/`orWhere` behavior — `orWhere` is a flat OR (no automatic grouping), so naive expansion can produce wrong precedence; prefer `whereRaw`.
- Always append `id` as the final, deterministic tie-break in both the ORDER BY and the seek predicate.
- `nextPosition` encodes the last returned row's sort values + id; `previousPosition` encodes the reverse anchor (or null on the first page).

## Requirements (Test Descriptions)
- [x] `it applies no seek predicate on the first page`
- [x] `it applies a seek predicate built from the decoded anchor on later pages`
- [x] `it orders by the sort fields with a deterministic id tie-break`
- [x] `it reports hasNext by fetching one extra row`
- [x] `it encodes the next position from the boundary entity values via the extractor`
- [x] `it throws IncompatiblePositionException when given an offset token`
- [x] `its page does not implement the random-access interface`

## Acceptance Criteria
- `KeysetPage` is a `Page` but NOT a `RandomAccessPageInterface`.
- All requirements have passing tests against a fake query builder + fake entities.
- No decrease in coverage.

## Implementation Notes

- `KeysetPaginationStrategy` returns base `Page` directly (no `KeysetPage` subclass needed — no extra behavior).
- `MissingCursorValueExtractorException` added in `src/Exceptions/` — thrown when `hasNext` is true but no extractor was supplied.
- Seek predicate built via `whereRaw("(col1, id) > (?, ?)", [...])` — row-value comparison (PostgreSQL). Column identifiers validated against `/^[a-zA-Z_][a-zA-Z0-9_]*$/` before interpolation; values always bound as parameters.
- Strategy implements `PaginationStrategyInterface<Entity>` (same as `OffsetPaginationStrategy`) rather than a generic `<TEntity>` to match codebase conventions.
- 9 tests total (7 required + 2 extra coverage tests); all pass with phpstan level 8 clean.
