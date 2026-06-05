# Plan: Pagination (engine + catalog listing)

## Created
2026-06-04

## Status
completed

## Objective
Build a headless, entity-agnostic pagination engine (`packages/criteria`) and wire it into the catalog storefront product listing with merchant-configurable strategy/presentation/count options, numbered pagination plus server-fragment-driven load-more/infinite scroll, and SEO-correct crawlable links.

## Related Issues
none

## Discovery Notes
Findings from Phase 1 codebase exploration that shaped this plan:

- **Config system** — Config classes are plain POPOs with `#[Config(key: '…')]` attributes; per-scope overrides use `#[Scoped(axes: [...])]`, auto-discovered at boot by `config-scope`. Field types are scalar (`string|int|bool|float|mixed`); array fields (`allowedPageSizes`, `allowedSorts`) are typed `array`/`mixed` and stored as JSON. Resolution is via `ConfigResolverInterface::resolved(string $configClass, string $field)`. Existing scope axes are only `market` and `channel` (see `packages/scope/config/scope.php`).
- **No customer/session scope exists.** Per clarification, customer-level preferences are **out of scope for v1** — merchant config drives size/sort/presentation; no new scope axis, no cookie persistence, no shopper switcher UI.
- **Storefront** — Latte templates driven by a Layout DSL (`packages/catalog-storefront/layout/category_show.php` using `Provide`/`Place`/`Slot::repeat`/`Source::query(...)`). Components are PHP classes returning `ExtensibleData` DTOs (`ProductGridComponent` → `ProductGridData`, `ProductCard` → `ProductCardData`). Rendering is orchestrated by `MarkommerceLayoutMiddleware` → `Renderer::render(...)`. The middleware looks up a `PreparedTree` by handle key `ControllerFQCN::action`, runs the controller for side effects, honors any non-2xx controller response as a short-circuit, then renders the WHOLE tree and returns `Response::html` (the controller's 200 body is discarded). `Source::query` supports casts `int|string|bool` only. A FRAGMENT is rendered by authoring a SEPARATE `Layout` file with its own handle whose root template is just the grid (no `OneColumnLayout` chrome) — you cannot render a sub-component of an existing tree in isolation. Feature tests build the layout pipeline manually (compile → `Renderer` + fakes) — see `CategoryLayoutTest`. The array-backed `Fake*Repository` doubles do NOT implement `query()`/joins, so real pagination needs a DB-backed test or a query-builder spy. There is a Vite + Lit (Web Components) JS pipeline with a configured vitest + happy-dom runner (`vite.config.ts` `test` block, co-located `mk-*.test.ts`); no pagination components yet.
- **Canonical/`<head>` injection has no existing path.** `base.latte` has `{block head-extra}` but `1column.latte` does not override it, and content-slot components cannot write into the base head. Task 018 must use a response `Link: rel=canonical` header (verify it survives the layout middleware re-wrap) or add a head template hook.
- **`RepositoryQueryBuilder`** cannot return raw rows AND hydrated entities in one execution (`get()` vs `getEntities()` are separate). Keyset cursor values are therefore read from the **hydrated boundary entity's public properties** via a caller-supplied extractor, constraining keyset sort keys to columns addressable as entity properties. Joins are explicit 4-arg `(table, first, operator, second)`; `where` is 3-arg `(column, operator, value)`. There is NO row-value/tuple `where` helper — keyset's `(a,b,id) > anchor` seek must use `whereRaw` (pgsql row-value comparison).
- **COUNT drops JOINs (verified).** `PgSqlQueryBuilder::count()` → `runAggregate()` emits `SELECT COUNT(*) FROM <table>` + WHERE only; any `join()` on the builder is NOT included. So `RowCounterInterface`/`ExactRowCounter` over a JOIN-filtered query is wrong/erroring. The catalog category total must come from a join-safe count (count `catalog_product_category` for the category directly), not from the joined product builder. (Tasks 006/007/011/012.)
- **Sort columns must be REAL.** `Product` (`catalog_products`) has only `id`, `sku`, `name`, `description`, `price_amount`; there is NO `created_at`/`price` column. `catalog_product_category` originally had NO ordering column — **Task 020 adds a curated `position` column** (per the user's decision to restore merchant-curated ordering). Allowed sorts are therefore `position` (default, → `catalog_product_category.position`), `name`, `sku`, `price` (→ column `price_amount`), with `id` as the universal tie-break. **Keyset caveat:** `position` lives on the join table, not the `Product` entity, so it is sortable under OFFSET only; the keyset path restricts to entity-addressable keys (`name`/`sku`/`price`). (Tasks 010/011/012/008/020.)
- **`#[Config]`/`#[Scoped]` deps.** `#[Config]` is `Markommerce\Config\Attributes\Config`; `#[Scoped]` is `Markommerce\Scope\Attributes\Scoped` (package `markommerce/scope`). `packages/catalog/composer.json` does NOT yet require `markommerce/config` or `markommerce/scope` — Task 010 must add both. (Mirrors `packages/currency`.)
- **N+1 confirmed** — `CategoryAssignmentService::productsInCategory()` loops `productRepository->find()` per assignment; replaced by a single join query through the engine.
- **Exceptions** extend `MarkoException` (in `marko/core`) with `message`/`context`/`suggestion` and static factory methods.

### Resolved clarifications
1. **Customer preferences:** skipped for v1 (merchant config only).
2. **Load-more/infinite transport:** server-rendered HTML fragments via the existing Latte/layout pipeline (no JSON API, no client-side card templating).
3. **Counters:** build `ExactRowCounter` + `EstimatedRowCounter` now; define `RowCounterInterface` so `CachedRowCounter` drops in later. Catalog default count mode = `exact`.

### Config-driven presentation switch (verified design)
The merchant flips `CatalogPaginationConfig.presentation` and the category page changes. The value travels: config → `PaginationOptionsResolver` (validates combo) → `ProductGridData.presentation` → `product-grid.latte` selects controls. Compatibility rule, enforced loudly in Task 011:
- `numbered` requires a random-access strategy (`offset`, the default). `numbered + keyset` is rejected with a loud config error.
- `load_more` and `infinite` need only `Page.nextPosition`, so they work over either strategy.
- All three render the same crawlable `?page=N` links; load-more/infinite enhance client-side by fetching server-rendered fragments. Task 017's feature test asserts each config value renders the correct controls.

## Scope

### In Scope
- New `packages/criteria` engine: value objects (`SortDirection`, `SortField`, `Sort`, `PageRequest`, `Page`), contracts (`PaginationStrategyInterface`, `RandomAccessPageInterface`, `RowCounterInterface`), opaque versioned position codec, `OffsetPaginationStrategy` (+ random-access page), `KeysetPaginationStrategy`, `ExactRowCounter`, `EstimatedRowCounter`, loud exceptions, `module.php` bindings, README.
- Catalog: `CatalogPaginationConfig`; config-driven strategy/counter selection + strategy×presentation validation + page-size/sort whitelisting & clamping + `maxPageDepth` enforcement; paginated category-products service that fixes the N+1 via a single join query.
- Storefront: paginated `ProductGridComponent`/`ProductGridData` + layout query-param wiring; numbered pagination Latte component with crawlable `?page=` links; config-driven presentation switch (numbered/load-more/infinite); server-rendered fragment endpoint for subsequent pages; Lit web components for load-more + infinite scroll (with accessible fallback); SEO (per-page self-canonical, optional `?view=all` gated by threshold, `410 Gone` beyond `maxPageDepth`).

### Out of Scope
- Customer/session scope axis, per-customer preference persistence (cookie/account), shopper-facing presentation/size/sort switcher UI.
- `CachedRowCounter` and its cache/invalidation infrastructure (interface kept ready).
- Admin order-grid pagination (engine kept general enough for it per "design for two"; not implemented).
- JSON pagination API (transport is HTML fragments).

## Success Criteria
- [ ] `packages/criteria` is a standalone Marko module with offset + keyset strategies and exact + estimated counters, fully unit-tested.
- [ ] Category listing is paginated through the engine with no N+1 (single join query).
- [ ] Merchant config switches strategy, presentation, count mode, page size, sort, and depth cap; out-of-whitelist size/sort and invalid strategy×presentation combos fail or clamp loudly.
- [ ] Flipping `presentation` between numbered / load_more / infinite changes the rendered category page, proven by a feature test (Task 017).
- [ ] Numbered pagination renders crawlable `?page=` links; load-more/infinite append server-rendered fragments; bots get a canonical default with per-page self-canonicals.
- [ ] Requests beyond `maxPageDepth` return `410 Gone`.
- [ ] All tests passing; PHPStan level 8 clean; phpcs/php-cs-fixer clean; coverage ≥ 80%.
- [ ] Code follows project standards (strict types, no `final`, readonly, constructor injection, loud exceptions with `@throws`).

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Pagination package scaffold + Sort value objects | - | completed |
| 002 | PageRequest value object + page-size guard | 001 | completed |
| 003 | Engine contracts + loud exceptions | 001, 002, 005 | completed |
| 004 | Opaque versioned position codec | 003 | completed |
| 005 | Page value object (sequential floor) | 001 | completed |
| 006 | OffsetPaginationStrategy + random-access page | 002, 003, 004, 005 | completed |
| 007 | Exact + Estimated row counters | 003 | completed |
| 008 | KeysetPaginationStrategy + keyset page | 002, 003, 004, 005 | completed |
| 009 | Engine module.php bindings | 006, 007, 008 | completed |
| 010 | CatalogPaginationConfig config class | 020 | completed |
| 011 | Catalog pagination options resolver (select, clamp, depth, validate combo) | 009, 010 | completed |
| 012 | Paginated category-products service + N+1 fix | 011, 020 | completed |
| 013 | ProductGrid component/DTO + layout query-param wiring | 012 | completed |
| 014 | Numbered pagination Latte component | 013 | completed |
| 015 | Server-rendered page-fragment endpoint | 012, 013 | completed |
| 016 | Lit load-more + infinite-scroll web components | 015 | completed |
| 017 | Config-driven presentation switch + end-to-end feature tests | 014, 016 | completed |
| 018 | SEO: per-page canonical, view=all, 410 depth cap | 013, 017 | completed |
| 019 | pagination package README | 009 | completed |
| 020 | Add curated `position` column to category assignments | - | completed |

## Architecture Notes
- **Three orthogonal concerns** kept separate: strategy (offset/keyset), counting (exact/estimated/…cached-later), presentation (numbered/load-more/infinite). Strategy and counter are selected in the catalog layer by config value (`PaginationOptionsResolver`), not by global DI rebinding — so the engine's default bindings stay generic while catalog uses offset+exact.
- **Engine defaults** (`packages/criteria/module.php`): `PaginationStrategyInterface => KeysetPaginationStrategy` (safe generic default for unknown consumers), `RowCounterInterface => ExactRowCounter`. Catalog deliberately overrides to **offset + numbered + exact** for the product listing (storefront SEO + numbered-nav convention).
- **`Page` is the floor**: items + size + next/previous position + `hasNext()`. Random access (`currentPage/totalPages/totalItems/positionForPage`) is the optional `RandomAccessPageInterface`, implemented only by the offset page. Numbered presentation type-depends on it; load-more/infinite depend only on `Page`. This encodes the offset-vs-keyset asymmetry in the type system and is why `numbered + keyset` is rejected.
- **Position tokens** are opaque, versioned, base64-encoded payloads carrying a type tag (`offset`|`keyset`). A strategy handed a foreign-type token throws `IncompatiblePositionException`.
- **Keyset cursor values** are extracted from the hydrated boundary entity via a caller-supplied value extractor (catalog passes one mapping sort keys → entity properties), avoiding entity-metadata coupling in the engine and the raw-vs-hydrated single-execution limitation.
- **`size + 1` fetch** determines `hasNext()` without a COUNT; counting is only paid for random-access totals.
- **Fragment endpoint** reuses the Latte/layout renderer to emit just the product-grid markup for a given page; Lit components fetch and append it, updating the URL via the History API. Crawlable `<a href="?page=N">` links exist in the server HTML regardless of presentation mode.

## Risks & Mitigations
- **Keyset boundary-value extraction** (raw-vs-hydrated limitation): mitigated by reading sort values off hydrated entity properties via a caller-supplied extractor; documented constraint that keyset sort keys must be entity-addressable.
- **Frontend tasks (Latte + Lit) are hard to unit-test in the Pest/PHP harness**: verify fragment output and the presentation switch via PHP layout/feature rendering tests; JS behavior (infinite-scroll IntersectionObserver, History API) verified via the Vite build plus manual check — called out in tasks 016/017.
- **Estimated counter portability** (pgsql `reltuples`/`EXPLAIN`): falls back to exact when no estimate is available; only used when the merchant opts into `countMode: estimated`.
- **Global vs catalog strategy binding** conflict: avoided by config-driven selection in `PaginationOptionsResolver` rather than catalog rebinding the global Preference.
- **Invalid config combos** (e.g. `numbered + keyset`): rejected loudly at resolve time (Task 011) rather than rendering a broken page.
- **Keyset extractor vs interface signature**: `PaginationStrategyInterface::paginate()` (Task 003) is fixed to a single signature used by offset, keyset, and the default container binding (Task 009). Recommended: `paginate(query, pageRequest, ?CursorValueExtractor $cursorExtractor = null)` — offset ignores it, keyset requires it (throws loudly if null when a position exists). This keeps one resolvable binding and avoids a per-construction extractor that would break the default binding.
- **Depth-cap 410 signal**: defined once in Task 011 (recommended `PageDepthExceededException`); both the main category controller (018) and the fragment endpoint (015) use the SAME symbol.
- **`COUNT` join-safety**: the offset total comes from a join-safe count owned by the caller/counter, never from `count()` on the joined builder (drops JOINs).
- **Branch base**: feature branch is based on `origin/develop` (includes marko 0.8 compat); PR targets `develop`.
