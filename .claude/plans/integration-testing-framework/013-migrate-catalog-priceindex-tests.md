# Task 013: Migrate catalog + price-index integration tests onto the harness

**Status**: completed
**Depends on**: 009, 010
**Retry count**: 0

## Description
Move the catalog and price-index integration tests onto the new harness (profiles + schema-from-entities + isolation + factories), replacing hand-written DDL `beforeEach`/`afterEach` and raw-SQL fixture functions. This is the lower-risk first half of the migration (the Tier2/Tier3 end-to-end migrations + helper deletion are task 017). Confirms the entity-driven schema fixes the `CatalogSeederTreeTest` drift failure.

## Context
- Tests to migrate in this task (verify green after EACH before moving to the next, so failures stay localized):
  1. `packages/catalog/tests/Feature/CategoryTreeIntegrationTest.php` — catalog-only; use `simple()`/a catalog profile. Lowest risk; proves the harness end-to-end.
  2. `packages/catalog/tests/Feature/CatalogSeederTreeTest.php` — currently FAILS (hand DDL lacks `price_amount`); the entity schema includes it → must go GREEN after migration. This is the headline drift fix.
  3. `packages/catalog-price-index/tests/Feature/Sorting/IndexedPriceSortOrderIntegrationTest.php` — price-index profile.
  4. `packages/catalog-price-index-market/tests/Feature/Sorting/ScopedIndexedPriceSortOrderIntegrationTest.php` — two-markets profile.
- Replace hand DDL + raw-SQL fixtures with `IntegrationTestCase` + a profile + the factories (task 010). Migrate behavior 1:1 — do NOT drop assertions. If a test relied on committed state (its own transaction), use truncate isolation mode (task 016).
- Choose the right profile per test (catalog-only → `simple()`/catalog profile; price-index → a profile that includes the price-index module; market → `twoMarketsTwoLocales()`).
- Do NOT delete the duplicated helpers yet — task 017 removes them after the Tier2/Tier3 migrations are also green (some still reference them until then). Catalog's `require-dev` on `markommerce/testing` was added in task 010.
- Run the affected-package suites sequentially first (rule out races), then `--parallel`.

## Requirements (Test Descriptions)
(Verified by the existing tests passing on the new harness — reuse their existing test names; checkboxes track migration outcomes.)
- [x] `it migrates CategoryTreeIntegrationTest onto IntegrationTestCase with no hand-written DDL`
- [x] `it makes CatalogSeederTreeTest pass via entity-driven schema`
- [x] `it migrates the indexed price sorting test onto a price-index profile`
- [x] `it migrates the scoped price sorting test onto a two-markets profile`
- [x] `it runs the migrated catalog and price-index tests under parallel without cross-worker failures`

## Acceptance Criteria
- All four tests pass on the new harness with original assertions intact; `CatalogSeederTreeTest` green (drift fixed).
- No hand-written DDL or raw-SQL fixtures remain in these files (factory/profile-driven).
- Parallel run clean for these packages.
- PHPStan level 8 clean (run with `php -d memory_limit=2G`).

## Implementation Notes

- All four files fully migrated to `IntegrationTestCase` + `StoreProfile`; no hand-written DDL or raw-SQL fixtures remain.
- `CategoryTreeIntegrationTest`: uses `StoreProfile::simple()`, `CategoryFactory`, and `$store->get(CategoryTreeService::class)` / `$store->get(CategoryService::class)`.
- `CatalogSeederTreeTest`: uses `IsolationMode::Truncate` (seeder's `insertBatch` commits internally when no transaction wraps it — Truncate mode avoids nested-transaction conflicts and ensures clean state). Resolves `CatalogSeeder` directly via `$store->get(CatalogSeeder::class)` (container autowires it from bound interfaces).
- `IndexedPriceSortOrderIntegrationTest`: uses `StoreProfile::of(vendorDir, 'markommerce/catalog-price-index', 'marko/database-pgsql')`; sort orders retrieved via `CategorySortOrderRegistry`; `ProductFactory::withIndexedPrice()` seeds price index rows.
- `ScopedIndexedPriceSortOrderIntegrationTest`: uses `StoreProfile::twoMarketsTwoLocales()`; `$store->inScope(market: 'us', ...)` sets market context; `setOverride()` on `ProductPriceIndexEntry` sets market-scoped overrides.
- Added `markommerce/testing: self.version` to `require-dev` in both `catalog-price-index/composer.json` and `catalog-price-index-market/composer.json`; ran `composer dump-autoload`.
- Helpers (`PostgresTestConnection`) NOT deleted — task 017 handles that.
- PHPStan level 8 clean on all changed files.
