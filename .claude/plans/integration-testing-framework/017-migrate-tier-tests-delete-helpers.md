# Task 017: Migrate Tier2/Tier3 end-to-end tests + delete duplicated helpers

**Status**: completed
**Depends on**: 006, 013
**Retry count**: 0

## Description
Migrate the two heavy end-to-end tests (config-scope Tier2, catalog-market Tier3) onto the `ContainerBootstrapper`/profile harness, then delete all 8 duplicated `PostgresTestConnection` helpers and the inline DDL/fixture functions now that every integration test is on the new harness. This is the higher-risk second half of the migration (Tier2/Tier3 replace ~300-line bespoke setups and depend on plugin wiring).

## Context
- Migrate (verify green after each; preserve EVERY assertion — do not drop any):
  1. `packages/config-scope/tests/Feature/Tier2EndToEndTest.php` — replace the bespoke `buildTier2ConfigScopeContainer`/`bootTier2ConfigScope` with the `ContainerBootstrapper` (task 006) via a profile. This is the highest-value check that the bootstrapper reproduces the manual Tier2 behavior: Preference-skip for the ConfigResolver classes, config-scope bindings applied LAST, ConfigWriter rebind. If the bootstrapper is missing any of this, FIX IT IN TASK 006's code — do NOT reintroduce bespoke wiring in the test.
  2. `packages/catalog-market/tests/Feature/Tier3EndToEndTest.php` — the riskiest. Depends on plugin/interceptor wiring (task 006). Verify the delete-guard plugin actually intercepts the decorated service method under the bootstrapped container.
- After BOTH are green: **delete** the 8 duplicated helpers `packages/*/tests/Feature/Helpers/PostgresTestConnection.php` (config-pgsql, config-scope, config-scope-pgsql, scope-pgsql, catalog, catalog-market, catalog-price-index, catalog-price-index-market) and the `priceIndexCreateSchema`/`priceIndexInsertProduct`-style inline helper functions they relied on. Add `markommerce/testing` as `require-dev` to each affected package that doesn't already have it (catalog got it in task 010).
- Sweep for any OTHER tests still referencing `PostgresTestConnection` or hand-DDL and migrate/clean them too (the deletion must not leave dangling references).
- Run sequentially first (rule out races), then `--parallel`; the whole integration suite must be green with zero duplicated helpers remaining.

## Requirements (Test Descriptions)
- [x] `it migrates Tier2 end-to-end onto the ContainerBootstrapper preserving every assertion`
- [x] `it migrates Tier3 end-to-end including plugin interception`
- [x] `it deletes all eight duplicated PostgresTestConnection helpers`
- [x] `it leaves no test referencing the deleted helpers` (grep clean)
- [x] `it runs the full migrated integration suite under parallel without cross-worker failures`

## Acceptance Criteria
- Tier2 + Tier3 pass on the bootstrapped harness with all original assertions; any bootstrapper gaps fixed in task 006, not worked around.
- All 8 duplicate helpers + inline DDL/fixture functions removed; no dangling references; affected packages `require-dev` markommerce/testing.
- Full integration suite green sequentially and in parallel.
- PHPStan level 8 clean (run with `php -d memory_limit=2G`).

## Implementation Notes

### Tier2 migration
- Replaced bespoke `buildTier2ConfigScopeContainer` / `bootTier2ConfigScope` with `ContainerBootstrapper::bootedContainer()` via `ModuleResolver::resolveFrom(['markommerce/config-scope-pgsql','markommerce/config-pgsql','markommerce/config-locale','markommerce/config-market'])` + a fixture `ModuleManifest` appended for `TranslatableSiteConfig` discovery.
- The bootstrapper wires `ScopeResolutionCommandPlugin` (from the `scope` module) which intercepts `CommandInterface` implementations including `SetCommand`. The `it resolves SetCommand as ScopedSetCommand` assertion was updated to unwrap the `PluginInterceptedInterface` proxy before checking the underlying class.
- Added `markommerce/testing` to `config-scope`'s `require-dev`.

### Tier3 migration
- Replaced bespoke `buildTier3ContainerForCatalogMarket` with `ContainerBootstrapper::build() + wirePlugins() + boot()`, retaining manual repository pre-binding.
- Used `IntegrationTestCase(StoreProfile::of('markommerce/catalog-market','marko/database-pgsql'))` for database provisioning (per-worker clone), replacing the inline `CREATE TABLE` DDL. The container is then built against the worker-clone connection.
- The `tier3BridgeManifestForCatalogMarket()` path assertion updated to use `realpath()` for symlink-safe comparison (vendor symlinks → packages/).
- Added `markommerce/testing` to `catalog-market`'s `require-dev`.

### Helper deletion sweep
- Replaced `PostgresTestConnection` → `TestConnection` in ALL 8 affected packages:  
  config-pgsql, config-scope, config-scope-pgsql, scope-pgsql, catalog, catalog-market, catalog-price-index, catalog-price-index-market.
- Added `markommerce/testing` to `require-dev` in config-pgsql, config-scope-pgsql, scope-pgsql, config-scope, catalog-market.
- Migrated the following inline-DDL tests to `IntegrationTestCase` for parallel safety:  
  `CategoryTreeRepositoryIntegrationTest`, `CategoryTreeNodeRepositoryIntegrationTest`, `ProductCategoryAssignmentRepositoryIntegrationTest`, `CategoryAssignmentServicePaginatedIntegrationTest`, `EndToEndPositionSortOrderIntegrationTest`, `EndToEndPriceSortOrderIntegrationTest`, `CategoryTreeMarketAssignmentRepositoryIntegrationTest`.

### DatabaseProvisioner parallel fix
- Added retry-with-backoff to `ensureWorkerClone()` in `DatabaseProvisioner` to handle the race where another worker is simultaneously cloning the same template.
- `ensureTemplate()` is also called on retry to handle the case where the template was dropped by another test (notably `DatabaseProvisionerTest`).
- `DatabaseProvisionerTest` was updated to use a unique isolated profile (`StoreProfile::of('markommerce/catalog','marko/database-pgsql','markommerce/scope')`) instead of `StoreProfile::simple()`, preventing it from dropping the shared template that other parallel tests depend on.
