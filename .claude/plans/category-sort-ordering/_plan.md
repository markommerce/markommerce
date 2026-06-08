# Plan: Extensible Category Sort Ordering

## Created
2026-06-08

## Status
completed

## Objective
Introduce a registry-based "sort order" abstraction for category product listings so developers can register additional `ORDER BY` options. For v1 only two orders ship: Position (default, from the category assignment) and Price (asc/desc, contributed by the price-index packages). Fixes the existing broken `defaultSort='position'`.

## Related Issues
none

## Discovery Notes
Settled design (agreed with user during brainstorm — do not re-litigate):

- **Registry pattern**, mirroring `packages/catalog/src/Pricing/PriceContributorRegistry.php` + the `module.php` `singletons`/`boot` wiring. NOT a config-enum.
- **v1 ships only `position` (default) and `price_asc`/`price_desc`.** Name/SKU orders are deferred but become one-line `ColumnSortOrder` registrations later. Separate keys per direction (no `?dir=` param).
- Consequence of position + price only: **no available order supports keyset** for v1 (position is on the join table, price isn't a `Product` property). The keyset strategy is therefore effectively unusable on category listings until a keyset-capable order (e.g. name/sku) is re-added; selecting any current order under keyset fails loudly. The `supportsKeyset()` machinery is retained for that future.
- **Dependency direction stays `catalog-price-index → catalog`.** Catalog must NOT require the price-index package (confirmed circular). Registry + interface + position/name/sku orders live in `catalog`; `IndexedPriceSortOrder` is registered by `catalog-price-index`'s `module.php` boot.
- **Market scope layering**: catalog only = no price sort; `+catalog-price-index` = base `amount` sort; `+catalog-price-index-market` = market-scoped sort via a `#[Preference]` override (mirror `ScopedIndexedMarketsProvider`).
- **Keyset compatibility**: each sort order declares `supportsKeyset()`. Indexed price AND position are offset-only (price isn't a `Product` property; `position` lives on the join table — neither is readable by `ProductCursorValueExtractor`). Selecting an incompatible sort under the keyset strategy errors loudly.
- **NULLs**: non-indexed products have `amount = NULL`. Price ordering must emit `NULLS LAST` in both directions.

Key technical findings from discovery:

- `Markommerce\Criteria\Sort\SortField` is `readonly class { string $column; SortDirection $direction }`. Both `OffsetPaginationStrategy` and `KeysetPaginationStrategy` apply ordering via `$query->orderBy($field->column, $field->direction->value)` then a fixed `id ASC` tie-break. So `SortField` must gain an optional raw expression + NULLS placement, and the strategies must use `orderByRaw` when set.
- **The marko query builder has NO `NULLS LAST` support (verified).** `PgSqlQueryBuilder::buildOrderByClause()` emits only `"<col-or-expr> <direction>"` with direction forced to `ASC`/`DESC`; you cannot pass `"ASC NULLS LAST"` (coerced to `ASC`) nor append `NULLS LAST` to the expression (the builder always appends ` <direction>` after it). **NULLS-last is therefore achieved with a companion sort field: order by `(<expr>) IS NULL ASC` first (false<true ⇒ non-NULLs first), then the real `<expr> <direction>`.** Task 001 makes the strategies expand a `NullsPlacement::Last` field into these two `orderByRaw` clauses.
- Marko's `RepositoryQueryBuilder` (`marko/packages/database`) provides `leftJoin(table, first, op, second)`, `orderBy(col, dir)`, `orderByRaw(expr, dir)`, `selectRaw`, `whereRaw`, `whereJsonContains/Exists`. `orderByRaw` only checks a denylist (`;`, `--`, `/* */`, backtick) via `IdentifierValidator::assertNoDangerousPatterns`, so `COALESCE(...)`, `->>'amount'`, `::numeric`, `(... IS NULL)` are all accepted.
- `PaginationOptionsResolver::resolve()` currently validates `?sort=` against the `allowedSorts` string array and bakes `new SortField($rawToken)` straight into a `PageRequest` — so the token is used as a literal SQL column. `defaultSort='position'` is therefore broken: `position` lives on `catalog_product_category`, not `catalog_products`; tests only pass by hand-passing the fully-qualified `catalog_product_category.position`.
- `ResolvedPaginationOptions` is a readonly DTO carrying a pre-built `PageRequest`. JOINs + column mapping are category-specific and can't be assembled in the resolver, so the resolved sort-order object must travel to `CategoryAssignmentService`, which adds JOINs and builds the `PageRequest` there.
- **Dropping `pageRequest` from `ResolvedPaginationOptions` breaks more call sites than the brainstorm listed (full audit).** Production: `CategoryAssignmentService` (172/179-180), `ProductGridComponent` (113, `pageRequest->size`), `CategoryController` (102-103 size, 108-109 `sort->fields[0]->column`). Tests/fakes: `PaginationOptionsResolverTest` (60-83 asserts, AND 105-114 which now THROWS under the keyset guard), `CategoryAssignmentServicePaginatedIntegrationTest` (`makeOffsetOptions` 134, inline 277), and five storefront feature fakes (`CategoryControllerTest`, `Tier1EndToEndTest`, `CategoryPageFragmentTest`, `CategoryLayoutTest`, `CategorySeoTest`) plus `catalog-storefront-scope`'s `ScopedProductGridComponentTest` (225). Each is now assigned to a task (004 / 005 / 008).
- **`#[Preference]` only swaps container-resolved bindings, not `new`.** The price-index boot must resolve the sort-order class(es) through the container (closure-injected, like catalog's `BasePriceContributor`), and the two price directions ship as TWO concrete classes so the market layer can `#[Preference]`-replace each.
- `CategoryAssignmentService::paginatedProductsInCategory()` builds the JOIN query (`catalog_products` ⨝ `catalog_product_category` filtered by category) and hands it to a strategy. The assignment JOIN is intrinsic, so `PositionSortOrder` needs no extra join — it just references `catalog_product_category.position`.
- Storefront `ProductGridComponent` already depends on `catalog-price-index` (`ProductPriceIndexRepositoryInterface`) and sources `?sort=` via `Source::query('sort', '', 'string')` in `CategoryProductGridLayout`. Pagination URLs already preserve `sort`.
- Price index table `catalog_product_price_index`: `product_id`, `amount decimal(20,4) NULL`, `currency_code`, `scopes JSONB` (per-market overrides like `{"market:us":{"amount":"12.3400"}}`).

## Scope

### In Scope
- `SortField` extension (raw expression + NULLS placement) and strategy support in `criteria`.
- `CategorySortOrderInterface` + `CategorySortOrderRegistry` in `catalog`.
- Reusable `ColumnSortOrder` in `catalog`; register only the `position` default for v1.
- Resolver + `ResolvedPaginationOptions` refactor to carry the selected sort order; loud error on keyset-incompatible sort.
- `CategoryAssignmentService` applies the sort order (joins + sort fields).
- `IndexedPriceSortOrder` (price_asc/price_desc, NULLS LAST) registered by `catalog-price-index`.
- Market-scoped price override in `catalog-price-index-market` via `#[Preference]`.
- Storefront sort dropdown sourced from the registry.
- Fix the broken `defaultSort='position'`.

### Out of Scope
- **Name/SKU (and any non-price/position) sort orders** — deferred; trivially re-addable via `ColumnSortOrder`.
- Keyset cursor support for price/position sorting (offset-only for v1; loud error otherwise). With only position+price shipping, keyset is effectively unusable on category pages for v1.
- Per-customer remembered sort preferences.
- Multi-column user-selectable sorting (each registered order may emit multiple `SortField`s internally, but the UI picks one order).
- New pricing/index storage; we read the existing index.

## Success Criteria
- [ ] Developers can register a new sort order from any package via `CategorySortOrderRegistry`.
- [ ] Default category ordering is by assignment position and works without hand-qualified columns in tests.
- [ ] With `catalog-price-index` installed, `?sort=price_asc` / `price_desc` order by indexed base amount with non-indexed products last.
- [ ] With `catalog-price-index-market` installed, price ordering respects the active market's JSON override.
- [ ] Selecting a keyset-incompatible sort under the keyset strategy throws a loud exception (message/context/suggestion).
- [ ] Storefront renders a sort dropdown from the registry and preserves the selection across pages.
- [ ] All tests passing; PHPStan level 8 clean; code follows standards.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | criteria: SortField raw expr + NULLS placement; strategy orderByRaw | - | completed |
| 002 | catalog: CategorySortOrderInterface + CategorySortOrderRegistry | - | completed |
| 003 | catalog: ColumnSortOrder + position default + module wiring | 002 | completed |
| 004 | catalog: resolver + ResolvedPaginationOptions + config + keyset loud error | 001, 002, 003 | completed |
| 005 | catalog: CategoryAssignmentService applies sort order (joins + fields) | 001, 004 | completed |
| 006 | catalog-price-index: IndexedPriceSortOrder (NULLS-last via IS NULL companion) + boot register | 001, 002, 003 | completed |
| 007 | catalog-price-index-market: market-scoped price override via #[Preference] | 001, 006 | completed |
| 008 | catalog-storefront: sort dropdown from registry, wired through grid | 003, 004, 005 | completed |
| 009 | end-to-end integration tests across the stack | 005, 006, 008 | completed |

## Architecture Notes
- `CategorySortOrderInterface`: `key(): string`, `label(): string`, `supportsKeyset(): bool`, `prepareQuery(RepositoryQueryBuilder $query): void` (adds JOINs), `sortFields(): list<SortField>`.
- The resolver selects the order and validates keyset compatibility; the service applies joins + builds the `PageRequest` from `sortFields()`. The strategy's existing `id ASC` tie-break preserves determinism.
- Registry is the source of *available* orders (installed packages decide what's available); config `defaultSort` + optional `enabledSorts` gate default/exposure, validated against the registry with a position fallback.
- Market override mirrors `ScopedIndexedMarketsProvider`: `#[Preference(replaces: IndexedPriceSortOrder::class)]` ordering by `COALESCE(scopes->'market:xx'->>'amount', amount)` for the active market.

## Risks & Mitigations
- **Criteria SortField change has cross-cutting impact**: keep new params optional with backward-compatible defaults; existing `SortField(column, direction)` call sites unchanged.
- **NO native NULLS LAST in the query builder**: achieved via a companion `(<expr>) IS NULL ASC` clause emitted by the strategies (task 001), not the `NULLS LAST` keyword. Both offset AND keyset strategies are updated symmetrically.
- **Raw COALESCE / JSON expression SQL-injection & type safety**: expressions are code-defined constants (never user input); market identifiers come from the scope registry, not the request. The `->>'amount'` JSON accessor returns TEXT — MUST be cast `::numeric` or ordering is lexicographic (`"100" < "9"`). Both invariants documented in tasks 006/007.
- **Keyset under v1 is unusable on category pages**: with only `position`+`price` (all `supportsKeyset()=false`), setting `strategy=keyset` makes the resolver throw on EVERY category request (even without `?sort=`). The default config strategy stays `'offset'`, so the out-of-the-box path never throws. Do NOT change the default. The existing `'resolves the keyset strategy kind'` resolver test breaks and is reworked in task 004.
- **ResolvedPaginationOptions shape change breaks ~12 call sites** (full list in Discovery Notes), not just `makeOffsetOptions`: each is assigned to tasks 004/005/008.
- **`#[Preference]` does not intercept `new`**: price sort orders must be container-resolved in boot and ship as two concrete classes so the market layer can replace each per direction.
- **Market override correctness depends on the active market path format** (`market:xx`): derive it from the scope resolver, mirroring existing market code, rather than hardcoding.
