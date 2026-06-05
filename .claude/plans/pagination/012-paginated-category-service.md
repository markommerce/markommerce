# Task 012: Paginated category-products service + N+1 fix

**Status**: complete
**Depends on**: 011, 020
**Retry count**: 0

## Description
Add a paginated method to `CategoryAssignmentService` that returns a `Page` of products in a category through the pagination engine, replacing the current N+1 (`find()` per assignment) with a single join query on `catalog_product_category`.

## Context
- File: `packages/catalog/src/Services/CategoryAssignmentService.php` (extend; keep the existing `productsInCategory()` or delegate it).
- New method, e.g. `paginatedProductsInCategory(int $categoryId, ResolvedPaginationOptions $options): Page`. Task 011's `PaginationOptionsResolver` produces the `ResolvedPaginationOptions` (pageRequest + presentation + `PaginationStrategyKind` + `CountMode`); the caller passes it in (the service does NOT read config). **This service constructs the concrete strategy from `options->strategyKind`** because it alone has the category context needed for a join-safe count:
  - `offset` → `new OffsetPaginationStrategy($positionCodec, $joinSafeCounter)` where `$joinSafeCounter` is built HERE from the category (see the COUNT bullet); pick `ExactRowCounter`/`EstimatedRowCounter` per `options->countMode`.
  - `keyset` → use the injected `KeysetPaginationStrategy` and pass the catalog `CursorValueExtractorInterface` impl to `paginate(...)`.
  Inject `PositionCodec` and `KeysetPaginationStrategy` (both container-resolvable) into the service constructor; build the offset strategy + counter per-call.
- **Confirmed real table/column names** (verified against entities): join `catalog_products.id = catalog_product_category.product_id`, filter `catalog_product_category.category_id = :id`. Concretely: `$query->join('catalog_product_category', 'catalog_products.id', '=', 'catalog_product_category.product_id')->where('catalog_product_category.category_id', '=', $categoryId)`. Note `RepositoryQueryBuilder::join()` is 4-arg `(table, first, operator, second)` and `where()` is 3-arg `(column, operator, value)` — already matched above. To avoid duplicate product rows from the join, add `->distinct()` or select-qualify on `catalog_products.*`.
- Sort columns and their logical keys: `position` → `catalog_product_category.position` (added by Task 020; the DEFAULT sort), `name` → `catalog_products.name`, `sku` → `catalog_products.sku`, `price` → `catalog_products.price_amount`; always append `catalog_products.id` as the deterministic tie-break. There is no `created_at` column. The `sortKey → column` mapping lives here / in Task 011. The default listing orders by `catalog_product_category.position ASC, catalog_products.id ASC`.
- **Keyset restriction:** the keyset path reads boundary values off the hydrated `Product`, so it cannot sort by `position` (which lives on the join table). When the keyset strategy is selected, the catalog sort key must be one of `name`/`sku`/`price`; reject (or fall back) on `position` + keyset. The default OFFSET strategy has no such restriction and supports `position`.
- **CRITICAL — COUNT does not include JOINs.** `PgSqlQueryBuilder::count()` (via `runAggregate()`) builds `SELECT COUNT(*) FROM <table>` + WHERE clause ONLY; it drops any `join()` applied to the builder. So an offset total over a category-joined query will either error (WHERE references the un-joined `catalog_product_category`) or count the wrong set. Task 012 must NOT rely on counting the joined builder. Instead, derive the offset total from a separate count that is join-safe — e.g. count `catalog_product_category` rows for the category directly (`assignmentRepository` count by category, or a dedicated count query on `catalog_product_category WHERE category_id = ?`). The `RowCounterInterface` passed to the offset strategy (Task 006) must therefore count the category-assignment set, NOT the joined product builder. Concretely: implement a small catalog `RowCounterInterface` (e.g. `CategoryProductRowCounter`) constructed with the category id (or a pre-built single-table count query on `catalog_product_category WHERE category_id = ?`) whose `count(RepositoryQueryBuilder $query): int` **ignores its `$query` argument** and returns the join-safe assignment count (a single-table `count()` is correct). Hand THIS counter to `new OffsetPaginationStrategy($positionCodec, $categoryProductRowCounter)`. For `countMode = estimated`, wrap it with `EstimatedRowCounter` (still single-table, so its estimate/exact-fallback is valid). Add a test: `it derives the offset total from a join-safe count not from the joined builder`.
- For keyset, supply the `CursorValueExtractor` mapping catalog sort keys (`name`, `sku`, `price`→`priceAmount`, `id`) to `Product` entity properties. Only entity-addressable properties are valid keyset keys (see Task 008).
- Single query for the page (plus one join-safe count only when the offset strategy needs totals) — assert no per-product `find()` calls.
- **Testing reality:** the existing catalog fakes (`FakeProductRepository`) back onto in-memory arrays and do NOT implement `query()`/`join()`; they cannot exercise a real join or the `size + 1` SQL. The "single join query / no N+1" requirement therefore needs a **DB-backed integration test** (Feature, pgsql) — OR a fake `RepositoryQueryBuilder`/connection spy that records the issued SQL. Pick one explicitly; do not assert N+1 absence against the array-backed fakes (they cannot model it). The depends-on for this is real: ensure the test harness has a pgsql connection (mirror existing `*IntegrationTest` feature tests in catalog) or build the query-builder spy.

## Requirements (Test Descriptions)
- [x] `it returns a page of products assigned to the category`
- [x] `it fetches the products in a single join query without per-product lookups`
- [x] `it applies the configured page size to the result`
- [x] `it orders products by the configured sort column with an id tie-break`
- [x] `it returns a next position when more products exist`
- [x] `it throws CategoryNotFoundException for an unknown category`
- [x] `it derives the offset total from a join-safe count not from the joined builder`

## Acceptance Criteria
- No N+1: a DB-backed feature/integration test (or query-builder/connection spy) asserts a single product query plus at most one join-safe count. Do NOT assert this against the array-backed `Fake*Repository` doubles.
- Returns an engine `Page<Product>`.
- All requirements have passing tests.

## Implementation Notes

The key fix was in `CategoryAssignmentService::paginatedProductsInCategory()`: the original code used `->select('catalog_products.*', ...)` which was rejected by `IdentifierValidator` (which does not allow `table.*` patterns). The solution was to explicitly select each `catalog_products` column using qualified identifiers (`catalog_products.id`, `catalog_products.sku`, etc.) so that the `ORDER BY "id"` tie-break added by both pagination strategies is unambiguous (PostgreSQL would otherwise see both `catalog_products.id` and `catalog_product_category.id` and report an ambiguous column error).

The `CategoryProductRowCounter` correctly ignores the joined query builder and counts directly from the assignment table, which satisfies the join-safe count requirement.
