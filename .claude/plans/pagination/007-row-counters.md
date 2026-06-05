# Task 007: Exact + Estimated row counters

**Status**: complete
**Depends on**: 003
**Retry count**: 0

## Description
Implement two `RowCounterInterface` drivers: `ExactRowCounter` (real `COUNT(*)` over the filtered query) and `EstimatedRowCounter` (fast planner/catalog estimate, falling back to exact when no estimate is available). `CachedRowCounter` is intentionally NOT built here — the interface leaves room for it later.

## Context
- Files: `packages/criteria/src/Counter/ExactRowCounter.php`, `packages/criteria/src/Counter/EstimatedRowCounter.php` (namespace `Markommerce\Criteria\Counter`).
- `ExactRowCounter` calls `RepositoryQueryBuilder::count()`. **Known limitation (must be documented in the counter's PHPDoc):** `marko/database`'s aggregate path emits `SELECT COUNT(*) FROM <table>` + WHERE and **does NOT include JOIN clauses**. So `ExactRowCounter` returns a correct total only for single-table (WHERE-filtered) queries. For JOIN-filtered result sets (e.g. catalog's category join, Task 012), the caller must supply a counter constructed over a join-safe query (e.g. counting `catalog_product_category` directly) rather than the joined product builder. Do not silently return a wrong number — document the constraint loudly in the class doc.
- `EstimatedRowCounter` uses a pgsql estimate (e.g. `EXPLAIN`/`pg_class.reltuples`) for unfiltered queries; when an estimate cannot be obtained (filters present, or estimate unavailable), it delegates to an injected exact counter. Keep the estimate source behind a small seam so it is testable with a fake.
- Tests use a fake query builder; do not require a live database in unit tests.

## Requirements (Test Descriptions)
- [x] `it returns the exact count from the query builder`
- [x] `it returns an estimated count when a planner estimate is available`
- [x] `it falls back to the exact count when no estimate is available`
- [x] `it falls back to the exact count when the query is filtered`

## Acceptance Criteria
- Both counters implement `RowCounterInterface`.
- All requirements have passing tests with fakes (no DB dependency in unit tests).
- No decrease in coverage.

## Implementation Notes
- `ExactRowCounter` delegates to `RepositoryQueryBuilder::count()` with the JOIN limitation documented in its PHPDoc.
- `EstimatedRowCounter` uses two injected seams: `CountEstimateSourceInterface` (returns `?int`) and `QueryFilterDetectorInterface` (returns bool). Falls back to exact when filtered or when estimate is null.
- Tests use `FakeRepositoryQueryBuilder` (subclass skipping parent constructor), `FakeExactCounter`, `FakeCountEstimateSource`, and `FakeQueryFilterDetector` — no DB connection required.
- PHPStan level 8 passes. 32 tests pass.
