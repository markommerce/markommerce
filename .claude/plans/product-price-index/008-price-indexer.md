# Task 008: catalog-price-index — `PriceIndexer` + `IndexedMarketsProviderInterface` (N+1-free)

**Status**: completed
**Depends on**: 006, 007
**Retry count**: 0

## Description
Build the `PriceIndexer` that populates the index by running the REAL batch pricing pipeline (`BatchPriceResolverInterface`) over chunks of products — once for the base/default amount and once per indexed market — then writing one row per product with per-market amounts in the `scopes` JSON via a single bulk upsert per chunk. A default `IndexedMarketsProviderInterface` returns no markets (base-only); the market bridge (T010) overrides it. The whole thing must be N+1-free: query count is independent of batch size.

## Context
- **Markets seam** `packages/catalog-price-index/src/Contracts/IndexedMarketsProviderInterface.php`: `markets(): list<string>`. Default impl `DefaultIndexedMarketsProvider` returns `[]` (no market overrides → index core knows nothing about markets). Bind it in `module.php`. T010 replaces it via `#[Preference]`.
- **Indexer** `packages/catalog-price-index/src/PriceIndexer.php` (+ interface). Deps: `ProductRepositoryInterface`, `BatchPriceResolverInterface`, `ProductPriceIndexRepository`, `IndexedMarketsProviderInterface`, `ScopeContext` (to set the ambient market per pass — only when markets exist), `CurrencyResolver` (for the row `currency_code`). API:
  - `reindexProducts(list<int> $ids): int` — returns the number of index rows written (products with a resolvable price); used by callers/reporting.
  - `reindexProduct(int $id): int` (delegates to `reindexProducts([$id])`, returns 0 or 1)
  - `rebuildAll(int $chunkSize = 500): int` — truncates, then iterates ALL product ids in chunks, calling `reindexProducts` per chunk; returns the TOTAL number of rows written (sum across chunks). The CLI (T009) reports this count, so it MUST be a return value, not void.
    - **All-ids enumeration:** `ProductRepositoryInterface` exposes `query(): RepositoryQueryBuilder` but NO "all ids" method. Do NOT load every `Product` entity into memory. Use `$productRepository->query()->selectRaw('id')->get()` (or page it) to stream the id list cheaply, then `array_chunk` the ids by `$chunkSize`. Each chunk then does its OWN `whereIn('id', $chunkIds)->getEntities()` load. (If `get()` returns raw rows, map to the `id` column.) Document this as the one unavoidable full-table id scan; it is O(1) statements, not per-product.
- **Per-chunk algorithm (N+1-free):**
  1. Load the chunk once: `$products = $productRepository->query()->whereIn('id', $ids)->getEntities()` (scope companions hydrated inline — no per-product queries). Key them by id: `[$p->id => $p]`.
     - **Why this is genuinely 1 query (verified):** `ProductScopedOverrides` is a `#[Table(extends: Product::class)]` extender — its `scopes` column lives in the SAME physical product table (the framework resolves an extender's `tableName` to the parent's). `getEntities()` hydrates the companion from columns already present in each row, issuing NO secondary query. So the chunk load is one `SELECT … WHERE id IN (…)`. The N+1-free proof therefore holds ONLY because of this same-table extension; if a future contributor needs data from a SEPARATE table it must batch-load it set-wise itself (the contributor contract). Do NOT use `->with(...)` for the scopes companion — it is not a relationship and `with()` would throw `unknownRelationship`.
  2. **Base pass:** with NO market scope set (clear `market` if present), `$baseMoney = $batchPriceResolver->resolve($productsById)` → one set-wise pipeline run for the whole chunk. Build an index entry per product: `amount = baseMoney[id]->amount()` (skip products with no money — no resolvable price), `currencyCode = baseMoney[id]->currency()->code`.
  3. **Per-market passes:** for each `$market` in `indexedMarketsProvider->markets()`: set `ScopeContext->in('market', $market)`, run `$batchPriceResolver->resolve($productsById)` again (one run per market, NOT per product), and for each resulting `Money` write it onto the entry's scopes JSON: `$entry->setOverride("market:$market", 'amount', $money->amount())`. Restore/clear the market afterwards.
     - **`setOverride` is called directly on `ProductPriceIndexEntry`** (it uses `HasScopes`), NOT through `ScopeResolver` — `ScopeResolver::setOverride` would require the `amount` property to be registered as scoped, which only happens in the T010 bridge. The raw `HasScopes::setOverride($signature, $property, $value)` write path needs no axis registration and produces the exact `{"market:X":{"amount":…}}` shape `ScopeResolver->resolved` later reads.
     - **`DefaultScopeGuard`:** `HasScopes::setOverride` calls `DefaultScopeGuard::assertWritable($signature)`. In tests, call `DefaultScopeGuard::reset()` in the relevant setup (mirror the pricing tests) so writes to `market:*` signatures are permitted. In production the guard is configured by the scope module boot.
     - **Single currency for the row:** the row's base `currency_code` is the base-pass currency. Per-market currency can differ (the "uses the per market currency override" case). v1 stores only `amount` overrides in `scopes`; a differing per-market currency is NOT separately indexed in v1 — note this limitation explicitly (the sort follow-up only reads `amount`). Do not silently write a wrong currency.
  4. One `upsertMany($entries)` for the whole chunk.
  - Total queries per chunk = 1 product load + 1 upsert + (contributor set-wise queries, fixed per pass) — independent of chunk size. Assert this with counting fakes across chunk sizes 1, 10, 100.
- **Null price:** products whose base amount is null are SKIPPED entirely (no index row) — matches `PriceUnavailableException` semantics; the sort read path treats missing rows as NULLS LAST (future).
- With the default (no-market) provider the indexer writes ONLY the base `amount` and an empty/absent `scopes` — proving the index degrades to a single base amount with no market bridge.

## Requirements (Test Descriptions)
- [x] `it writes one index row per product with the base amount and currency`
- [x] `it skips products that have no resolvable price`
- [x] `it writes per market amounts into the scopes json for each indexed market`
- [x] `it writes only the base amount when no markets are indexed`
- [x] `it reindexes a single product by id`
- [x] `it rebuilds the whole index in chunks after truncating`
- [x] `it returns the count of index rows written from rebuildAll`
- [x] `it loads each chunk of products with a single query regardless of chunk size`
- [x] `it runs the pricing pipeline once per market pass not once per product`
- [x] `it restores the ambient market scope after indexing`
- [x] `it upserts each chunk in a single bulk statement`

## Acceptance Criteria
- Query count per chunk is constant across chunk sizes 1/10/100 (asserted) — no N+1.
- Per-market amounts land in the `scopes` JSON in the `{"market:X":{"amount":…}}` shape `ScopeResolver` reads.
- With the default markets provider, only base amounts are written.
- PHPStan level 8 clean; phpcs clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
