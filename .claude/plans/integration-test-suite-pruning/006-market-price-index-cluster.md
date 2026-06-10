# Task 006: Prune market & price-index cluster

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
`InvariantMatrixTest` exercises position + indexed-price **ascending** result order + market-override sorting across all three profiles, and `MarketScopedPriceExpressionTest` pins the `::numeric` COALESCE as a unit. The per-package sort tests and the `Tier2/Tier3 EndToEnd` milestones largely duplicate this. Several boot-contribution tests need no DB.

**USER DECISION (keep one DB descending/NULLs-last guard):** No existing integration test asserts that a *descending* / NULLs-last sort spec produces correctly-ordered rows against real Postgres (the unit `IndexedPriceSortOrderTest` asserts only the spec; InvariantMatrix asserts only ascending result order). Therefore, while trimming this cluster, **retain exactly one real-SQL case** that seeds indexed + non-indexed products and asserts descending order with NULL prices placed last on the actual result rows. Consolidate it into `EndToEndPriceSortOrderIntegrationTest` (real factories + sort pipeline). Do not let the trims below remove the last descending/NULLs-last *result-order* guard.

## Context
- Canonical coverage: `packages/testing/tests/Feature/InvariantMatrixTest.php`; unit `packages/catalog-price-index-market/tests/Unit/Sorting/MarketScopedPriceExpressionTest.php` + `TypeCompatibilityRegressionTest.php`.
- Files:
  - `packages/catalog-scope/tests/Feature/Tier2EndToEndTest.php` (508 ln, FAKE in-memory connections)
  - `packages/catalog-market/tests/Feature/Tier3EndToEndTest.php`, `PriceAmountScopeResolutionTest.php`, `Pricing/ScopedProductBasePriceProviderTest.php`
  - `packages/catalog-price-index/tests/Feature/Sorting/IndexedPriceSortOrderIntegrationTest.php`, `EndToEndPriceSortOrderIntegrationTest.php`
  - `packages/catalog-price-index-market/tests/Feature/Sorting/ScopedIndexedPriceSortOrderIntegrationTest.php`, `Sorting/PreferenceTypeCompatibilityIntegrationTest.php`
  - `packages/currency-market/tests/Feature/BootContributionTest.php`, `packages/tax-market/tests/Feature/BootContributionTest.php`
- Keep: `catalog-market/.../CategoryTreeMarketAssignmentRepositoryIntegrationTest.php` (real SQL upsert/FK), `catalog-scope/.../CompanionPersistenceTest.php`, `ScopedIndexedMarketsProviderTest.php`.

## Requirements (verification assertions about the resulting suite)
- [x] `it deletes catalog-scope Tier2EndToEndTest entirely` — milestone using fake in-memory connections; boot/registry asserts are `ScopedFieldRegistry`/`ScopeRegistry` unit-level and its persistence round-trips duplicate `CompanionPersistenceTest`.
- [x] `it deletes IndexedPriceSortOrderIntegrationTest but preserves the descending/NULLs-last result-order guard` — the non-indexed-last **ascending** case is subsumed by `InvariantMatrixTest` `orders by indexed price ascending in profiles that include the price index`, so delete it. But per the USER DECISION, the **descending + NULLs-last result-order** case is the one DB guard to keep: migrate it (the assertion that descending sort orders real rows high→low with NULL-priced products last) into `EndToEndPriceSortOrderIntegrationTest` rather than dropping it, then delete the rest of this file. Net: this file is removed, its one descending/nulls case survives in EndToEnd.
- [x] `it trims EndToEndPriceSortOrderIntegrationTest to the paging case plus the descending/NULLs guard` — keep "preserves the selected sort across pagination pages" (unique keyset-boundary) **and** the one descending + NULLs-last result-order case migrated from `IndexedPriceSortOrderIntegrationTest` (per USER DECISION); delete the ascending and non-indexed-after-indexed-ascending cases (InvariantMatrix-covered).
- [x] `it trims ScopedIndexedPriceSortOrderIntegrationTest from five to one` — keep at most the real-SQL LEFT-JOIN guard; delete market-override-wins / fallback / numeric-cast / nulls-last. **Coverage attribution (verified):** market-override-wins → InvariantMatrix `reflects the active market price override in the two-markets profile`; numeric-cast → unit `MarketScopedPriceExpressionTest`; nulls-last result-order is now retained once at the non-market layer in `EndToEndPriceSortOrderIntegrationTest` (see above), so the market-scoped nulls-last case here is the redundant one to drop. Keep+note if the market-scoped expression path differs materially from the non-market one.
- [x] `it deletes PreferenceTypeCompatibilityIntegrationTest` — boots via `NullConnection` (no Postgres) and duplicates the `TypeCompatibilityRegressionTest` unit instanceof proof.
- [x] `it trims catalog-market Tier3EndToEndTest` — delete the 3 framework plumbing tests (PluginInterceptor ordering, bridge ModuleManifest path, PluginDiscovery registration); keep the 6 resolver/delete-guard behaviors, refocused onto `IntegrationTestCase` instead of the bespoke `beforeEach` bootstrap.
- [x] `it merges PriceAmountScopeResolutionTest into ScopedProductBasePriceProviderTest` — both assert per-market override resolve/fallback at adjacent layers; fold the unique lower-layer assertion in and delete the file. **DEPTH-SHIFT WARNING**: the source `PriceAmountScopeResolutionTest.php` is at `tests/Feature/` (uses `dirname(__DIR__, 2)` for its own `module.php` and `dirname(__DIR__, 3)` for `scope/module.php`); the merge target `tests/Feature/Pricing/ScopedProductBasePriceProviderTest.php` is one level deeper (uses `dirname(__DIR__, 3)` / `dirname(__DIR__, 4)`). When relocating the folded-in code, **increment every moved `dirname(__DIR__, N)` by 1** (2→3, 3→4) and re-run the package suite immediately to confirm the `require` paths still resolve.
- [x] `it downgrades currency-market and tax-market BootContributionTest` — no Postgres (manual boot + `InMemoryScopedConfigStorage`); ensure untagged and, if pure, relocate `Feature/ → Unit/`.
- [x] `it keeps every surviving DB case tagged and skip-guarded`.

## Acceptance Criteria
- `./vendor/bin/pest --group=integration-destructive packages/catalog-market packages/catalog-price-index packages/catalog-price-index-market packages/catalog-scope` green with DB.
- Deleted sort cases confirmed covered by `InvariantMatrixTest` + named unit tests.
- Refocused Tier3 behaviors still pass via `IntegrationTestCase`; no bespoke bootstrap helpers left orphaned.

## Execution (deletion/refocus task — no Red phase)
1. Run `./vendor/bin/pest --group=integration-destructive packages/catalog-market packages/catalog-price-index packages/catalog-price-index-market packages/catalog-scope` (DB) green first.
2. For each deleted sort case, open the named equivalent (InvariantMatrix case OR the specific unit test — note nulls-last/descending live ONLY in `IndexedPriceSortOrderTest`, not InvariantMatrix) and confirm before deleting; keep+note otherwise.
3. When refocusing Tier3 behaviors onto `IntegrationTestCase`, remove the bespoke `beforeEach` bootstrap closure only after the last behavior using it is migrated — grep to confirm no orphaned bootstrap helper remains.
4. Apply the depth-shift `+1` on the `PriceAmountScopeResolutionTest → Pricing/ScopedProductBasePriceProviderTest` merge (see requirement above).
5. Re-run suite green; phpcs on touched files.

## Implementation Notes
- Deleted `catalog-scope/tests/Feature/Tier2EndToEndTest.php` entirely (508 ln, fake in-memory connections, no DB).
- Deleted `catalog-price-index/tests/Feature/Sorting/IndexedPriceSortOrderIntegrationTest.php`; migrated its descending/NULLs-last test body into `EndToEndPriceSortOrderIntegrationTest.php` as a new standalone test "places non-indexed products last in descending order with NULL prices last".
- `EndToEndPriceSortOrderIntegrationTest.php` now has exactly 2 tests: the migrated descending guard + "preserves the selected sort across pagination pages". Ascending and non-indexed-after-indexed tests removed (InvariantMatrix-covered).
- `ScopedIndexedPriceSortOrderIntegrationTest.php` trimmed from 5 to 1 test (LEFT-JOIN guard kept). Removed: market-override-wins (InvariantMatrix), fallback-to-base (InvariantMatrix), numeric-cast (unit MarketScopedPriceExpressionTest), nulls-last (now in EndToEnd). Note: the market-scoped path's LEFT-JOIN behavior is distinct (uses COALESCE(json override, base)) and warrants its own real-SQL guard.
- Deleted `catalog-price-index-market/tests/Feature/Sorting/PreferenceTypeCompatibilityIntegrationTest.php` (NullConnection, duplicates unit TypeCompatibilityRegressionTest).
- `catalog-market/tests/Feature/Tier3EndToEndTest.php` refactored: removed 3 framework plumbing tests (PluginInterceptor ordering, bridge ModuleManifest path, PluginDiscovery registration), refocused `beforeEach` bootstrap from bespoke ContainerBootstrapper dance onto `IntegrationTestCase(tier3StoreProfile())`. All 6 resolver/delete-guard behaviors retained and pass.
- `catalog-market/tests/Feature/Pricing/ScopedProductBasePriceProviderTest.php`: folded the 2 lower-layer `ScopeResolver::resolved()` assertions from `PriceAmountScopeResolutionTest.php`. Depth-shift applied: source used `dirname(__DIR__, 3)` for scope module — target already uses `dirname(__DIR__, 4)`, so folded tests reuse existing `buildMarketPricingContainer()` / `bootMarketPricingModules()` helpers.
- Deleted `catalog-market/tests/Feature/PriceAmountScopeResolutionTest.php`.
- Moved `currency-market/tests/Feature/BootContributionTest.php` → `Unit/BootContributionTest.php` (no DB, InMemoryScopedConfigStorage; no group tags needed).
- Moved `tax-market/tests/Feature/BootContributionTest.php` → `Unit/BootContributionTest.php` (same reason).
- All surviving DB integration tests retain `->group('integration-destructive')` and `TestConnection::skipIfUnavailable()`.
- phpcs passes on all touched files.
