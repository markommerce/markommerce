# Plan: Catalog-owned pricing + batch pipeline + HasScopes price index

## Created
2026-06-05

## Status
completed

## Objective
Make pricing a scope-free, catalog-owned, batch-first contributor pipeline (single source of truth for display + indexing), and add a denormalized product price index that uses `HasScopes` (one row/product) — populated by an N+1-free bulk indexer. Sorting/consuming the index is a follow-up.

## Related Issues
none

## Discovery Notes
- **Today:** `packages/pricing` holds `PriceResolverInterface`/`PriceResolver` (uses `ScopeResolver->resolved($product,'priceAmount')` + `CurrencyResolver->base()`), `PriceContext`, `PriceUnavailableException` (thrown on a null amount — `PriceResolverTest` asserts this). Consumers: `catalog-storefront` (ProductGridComponent) + `catalog-storefront-scope`.
- **`ScopeResolver::resolved()` degrades** to the raw property when there's no companion/override. `HasScopes` (the `scopes` JSON column + companion) lives in `packages/scope` (`src/Storage/HasScopes.php`); `catalog-market` registers `Product.priceAmount` on the `market` axis via `ScopedFieldRegistry`.
- **Bridge pattern**: `currency-market`/`catalog-market` (thin, `module.php` `bindings`/`boot`/`singletons`). `#[Preference(replaces: X::class)]` overrides a binding. Boot-registered registries (e.g. `ScopedFieldRegistry`) populated in `module.php` boot.
- **Markets**: `ScopeRegistryInterface->getHierarchy('market')->paths()` (throws `UnknownAxisException` if absent). `ScopeContext->in('market',$m)`/`->clear('market')`.
- **DB**: `RepositoryQueryBuilder` has `whereIn`/`selectRaw`/`leftJoin`; bulk upsert via `connection->execute("INSERT … ON CONFLICT … DO UPDATE …")` (mirror `PgsqlScopedConfigStorage`). `Money::of(string|int, Currency)`; `$money->amount()`/`$money->currency()->code`. Commands auto-discovered by `#[Command]` scan (no `commands` key). Migrations live in the playground `database/migrations/`.

### Resolved decisions
1. **`catalog` owns the whole pricing pipeline**, scope-free: `pricing` is dissolved into `Markommerce\Catalog\Pricing\…`; catalog depends on money/currency, NOT scope. Base price comes from a `ProductBasePriceProviderInterface` (raw default in catalog → `price_amount`).
2. **`catalog-market` adds the scoped base price** via `#[Preference]` `ScopedProductBasePriceProvider` (reads the `scopes` companion). Install it (with scope/market) to get market-resolved prices; without it, prices are raw.
3. **Batch-first pipeline is the single source of truth**: `PriceContributorInterface` (set-wise, O(1) queries) + registry; `BatchPriceResolver` runs it over a `PriceBatch`; `PriceResolver::resolve` = batch of 1. Future tax/promo packages implement ONE contributor.
4. **Index uses `HasScopes`**: `ProductPriceIndexEntry` = `amount` + `currency_code` + `scopes` JSON, ONE row/product. `catalog-price-index` depends on `scope`. The **`market` axis is added by a bridge** (`catalog-price-index-market`), which also feeds the indexer the market list — so the index "knows nothing about markets" until that bridge is installed.
5. **Indexer v1**: `reindexProducts(ids)` + chunked `rebuildAll()` + `reindexProduct(id)`; bulk upsert; CLI; N+1-free (query count independent of batch size). No auto-observer / dirty-tracking (deferred).

## Scope

### In Scope
- **Migrate `pricing` → `catalog`** (namespaces + consumers + composer; remove the `pricing` package), behavior-preserving.
- **Decouple pricing from scope**: `ProductBasePriceProviderInterface` + raw default in catalog; scoped `#[Preference]` provider in `catalog-market`; catalog drops the scope dep.
- **Batch pipeline** in catalog: `PriceBatch`, `PriceContributorInterface` + registry + `BasePriceContributor`, `BatchPriceResolverInterface` + impl, `PriceResolver::resolve` delegates.
- **`catalog-price-index`** (new): `ProductPriceIndexEntry` (HasScopes) + migration + repo (bulk upsert), `PriceIndexer` (batch/chunked/N+1-free) + `IndexedMarketsProviderInterface` (default base-only), CLI rebuild, README.
- **`catalog-price-index-market`** (new bridge): register the index `amount` on the `market` axis + a `#[Preference]` `IndexedMarketsProvider` returning the market paths.

### Out of Scope
- **Sorting / consuming** the index (the listing sort is a follow-up; this plan only builds + populates it).
- Auto-invalidation observers / dirty-tracking / reindex queue (manual rebuild + explicit reindex API for v1).
- Actual tax / FX / promo / tier contributors (only the seam).

## Success Criteria
- [ ] `catalog` owns the pricing pipeline; `markommerce/pricing` is gone; consumers updated; catalog's composer does NOT require `markommerce/scope`.
- [ ] `PriceResolver::resolve()` behavior is unchanged WITH `catalog-market` installed (existing scoped tests pass, now in catalog-market); raw without it.
- [ ] A contributor implemented once affects both display and index.
- [ ] `PriceIndexer` reindexes a batch in a query count independent of batch size (no N+1); ONE row per product; per-market amounts live in the `scopes` JSON only when the market bridge is installed.
- [ ] `catalog:price-index:rebuild` works; index degrades to a single base amount with no market bridge.
- [ ] All tests passing; PHPStan level 8 clean; phpcs/php-cs-fixer clean.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Migrate `pricing` package into `catalog` (mechanical, behavior-preserving) | - | completed |
| 002 | catalog: base-price seam (`ProductBasePriceProviderInterface` + raw) + drop scope dep | 001 | completed |
| 003 | catalog-market: `ScopedProductBasePriceProvider` (`#[Preference]`) + scoped tests | 002 | completed |
| 004 | catalog: `PriceBatch` working object | 001 | completed |
| 005 | catalog: `PriceContributorInterface` + registry + `BasePriceContributor` | 002, 004 | completed |
| 006 | catalog: `BatchPriceResolverInterface` + impl + `PriceResolver` delegates | 003, 004, 005 | completed |
| 007 | catalog-price-index: package + `ProductPriceIndexEntry` (HasScopes) + migration + repo (bulk upsert) | 001 | completed |
| 008 | catalog-price-index: `PriceIndexer` + `IndexedMarketsProviderInterface` (N+1-free) | 006, 007 | completed |
| 009 | catalog-price-index: CLI `catalog:price-index:rebuild` | 008 | completed |
| 010 | catalog-price-index-market: register `amount` on market axis + markets provider | 007, 008 | completed |
| 011 | catalog-price-index: README | 007, 008, 009 | completed |
| 012 | catalog-storefront: use indexed price on category page | 009 | completed |

## Architecture Notes
- **Migration (001) is the serializing foundation** — everything else depends on it; keep it behavior-preserving (pure move + namespace + consumer + composer updates; pricing tests move with it). It runs first and alone.
- **Base-price seam**: `ProductBasePriceProviderInterface::amountsFor(list<Product> $products): array<int, ?string>` (id → raw decimal string or null). Raw default returns `$product->priceAmount`, market-agnostic — reads the AMBIENT scope context only via the scoped override (catalog-market). Pricing core never imports scope.
- **Ambient market**: the caller (indexer/storefront) sets `ScopeContext`; the pricing pipeline is context-agnostic. The scoped provider (catalog-market) reads `ScopeResolver` for the ambient market.
- **Null price** stays "no resolvable price": unseeded in the batch; `BatchPriceResolver` omits it; `PriceResolver::resolve` re-throws `PriceUnavailableException`; the indexer skips it.
- **`PriceContributorRegistry`** is a boot-registered shared **singleton**; the catalog module registers `BasePriceContributor`; future packages register theirs at boot with a priority.
- **Index = HasScopes, one row/product**: `ProductPriceIndexEntry` uses `HasScopes` (`amount`, `currency_code`, `scopes` JSON). The indexer computes the base (ambient/default) amount → `amount`, and for each market from `IndexedMarketsProviderInterface` computes the amount and writes it as a scope override into `scopes` (`{"market:us":{"amount":"19.99"}}`). One bulk upsert (multi-row `ON CONFLICT (product_id) DO UPDATE`) per chunk. Reading per-market (future sort) = `ScopeResolver->resolved($entry,'amount')` once `catalog-price-index-market` registers `amount` on the `market` axis.
- **`IndexedMarketsProviderInterface`** default (catalog-price-index) returns `[]` (base-only, no market overrides); `catalog-price-index-market` overrides it (`#[Preference]`) to return `ScopeRegistry->getHierarchy('market')->paths()` minus the default. So the index core depends on `scope` (for HasScopes) but NOT `market`; the market bridge adds the axis + the market list.
- **N+1 proof**: base pipeline reindexes any batch size in a fixed query count (1 batch load via `query()->whereIn('id', …)->getEntities()` with eager `scopes` companions + 1 bulk upsert); proven with fake repos counting load/upsert calls across batch sizes.

## Risks & Mitigations
- **`pricing→catalog` migration breaking autoload/consumers (001)**: isolate as the first, standalone task; behavior-preserving; the existing pricing test suite moves with it and must stay green before anything else runs. Update root `composer.json` + every `Markommerce\Pricing\` import.
- **Catalog now requires money/currency**: acceptable (catalog already conceptually prices products); confirm no circular dep (currency/money don't depend on catalog).
- **HasScopes write path**: the indexer must write per-market amounts into the entry's `scopes` JSON in the format `ScopeResolver` reads (`{"market:X":{"amount":…}}`); covered by a read-back test through `ScopeResolver` once the market bridge registers the axis.
- **Index requires scope (via HasScopes)**: intentional — a truly no-scope merchant doesn't install the index; `pricing`/`catalog` stay scope-free (the actual requirement).
- **Existing scoped `PriceResolver` tests**: move to `catalog-market` (the scoped provider) so catalog's own tests assert raw behavior; don't leave them asserting scope in catalog.
- **Branch base**: off `origin/develop`; PR targets `develop`.
