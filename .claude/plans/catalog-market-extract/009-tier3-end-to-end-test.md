# Task 009: Tier 3 end-to-end test (full container boot, Postgres-backed)

**Status**: completed
**Depends on**: 005, 006, 007
**Retry count**: 0

## Description
Add `packages/catalog-market-category-trees/tests/Feature/Tier3EndToEndTest.php` that boots the full Tier 3 module chain against a real Postgres database, creates two non-default trees + a default tree, assigns each non-default tree to a market via `CategoryTreeMarketAssignmentService`, resolves the active tree per market via `CategoryTreeMarketResolver`, and verifies the `CategoryTreeServiceDeletePlugin` blocks a `CategoryTreeService::deleteTree` call when the target tree still serves a market.

## Context
- Pattern reference: `packages/catalog-scope/tests/Feature/Tier2EndToEndTest.php`. Copy its scaffolding (`buildTier*Container`, manifest helpers, `DependencyResolver` usage, Postgres connection wiring) and extend the manifest list. **However, Tier2EndToEndTest does NOT wire up the Plugin interceptor** — it builds a `Container` and runs boot closures only. The Tier 3 test MUST extend this with explicit plugin wiring (see "Plugin interceptor wiring" below), otherwise the `CategoryTreeServiceDeletePlugin` will silently not fire and the delete-guard assertions will give a false-positive or false-negative.
- Manifests booted:
  - `marko/config`, `marko/core`, `marko/database`
  - `markommerce/scope`, `markommerce/scope-pgsql`
  - `markommerce/locale`
  - `markommerce/market` (new)
  - `markommerce/catalog`
  - `markommerce/catalog-scope`
  - `markommerce/catalog-locale`
  - `markommerce/catalog-market` (new, no-op boot)
  - `markommerce/catalog-market-category-trees` (new)
  - `markommerce/config`, `markommerce/config-pgsql`
- Real tables created via `PostgresTestConnection`: `catalog_category_trees`, `catalog_category_tree_market_assignments`, `catalog_categories`, `catalog_category_tree_nodes`. Inline `CREATE TABLE` statements mirror the catalog integration test's prior versions (which had the market table) — they now live in the new package.
- Test scenarios (each is a separate `it(...)` group: `integration-destructive`):
  1. Resolver picks per-market assigned tree
  2. Resolver falls back to default tree when no assignment exists
  3. Plugin guard blocks `deleteTree` when assignments exist
  4. Plugin guard allows `deleteTree` when no assignment exists
  5. `assignTreeToMarket` upsert: assigning a second tree to the same market replaces the first

### Plugin interceptor wiring (CRITICAL — without this the delete-guard test is meaningless)
The reference `Tier2EndToEndTest` builds a `Container` manually and never calls `setPluginInterceptor` — so resolving a target class from that container returns an UNPROXIED instance and `#[Before]` interceptors never fire. For the Tier 3 test the helper container builder must:

1. After `new Container(...)`, instantiate the registry/interceptor explicitly:
   ```php
   $pluginRegistry = new PluginRegistry();
   $interceptor = new PluginInterceptor($container, $pluginRegistry, new InterceptorClassGenerator());
   $container->setPluginInterceptor($interceptor);
   $container->instance(PluginInterceptor::class, $interceptor);
   $container->instance(PluginRegistry::class, $pluginRegistry);
   ```
2. Construct the bridge's `ModuleManifest` with the `path` argument set to the bridge package's absolute path (so `PluginDiscovery::discoverInModule()` can find `src/Plugins/`). Example:
   ```php
   $bridgeModule = require dirname(__DIR__, 2) . '/module.php';
   $bridgeManifest = new ModuleManifest(
       name: 'markommerce/catalog-market-category-trees',
       version: '1.0.0',
       require: $bridgeModule['require'] ?? ['markommerce/catalog' => '*', 'markommerce/market' => '*'],
       path: dirname(__DIR__, 2),  // ← REQUIRED for PluginDiscovery
       bindings: $bridgeModule['bindings'] ?? [],
   );
   ```
   Reference pattern: `packages/catalog-locale/tests/Feature/BootContributionTest.php` (manifest helpers), `packages/layout/tests/Feature/RendererPluginTest.php` lines 91-105 (interceptor wiring).
3. Run plugin discovery against the bridge manifest BEFORE resolving `CategoryTreeService`:
   ```php
   $pluginDiscovery = new PluginDiscovery();
   foreach ($pluginDiscovery->discoverInModule($bridgeManifest) as $definition) {
       $pluginRegistry->register($definition);
   }
   ```
4. ONLY THEN resolve `CategoryTreeService` from the container — the call triggers `PluginInterceptor::createProxy()` and the returned instance has `beforeDeleteTree` wired in.

**Do NOT** instantiate `CategoryTreeService` with `new CategoryTreeService(...)` — that bypasses interception and gives a false test signal. **Do NOT** copy `Tier2EndToEndTest`'s container builder verbatim — it lacks all four steps above.

## Requirements (Test Descriptions)
- [x] `it resolves the assigned non-default tree for each market that has an assignment`
- [x] `it falls back to the default tree for a market with no assignment`
- [x] `it throws DefaultTreeMissingException when neither an assignment nor a default tree exists`
- [x] `it throws TreeHasMarketAssignmentsException via the plugin when deleteTree is called on a tree that still serves a market`
- [x] `it allows deleteTree on a non-default tree with no market assignments`
- [x] `it replaces an existing assignment when assignTreeToMarket is called twice for the same market with different trees`
- [x] `it wires PluginInterceptor into the test container BEFORE resolving CategoryTreeService (otherwise the delete-guard test is meaningless)`
- [x] `it constructs the bridge ModuleManifest with the absolute filesystem path so PluginDiscovery can scan src/Plugins/`
- [x] `it discovers and registers CategoryTreeServiceDeletePlugin via PluginDiscovery::discoverInModule against the bridge manifest`

## Acceptance Criteria
- The test file lives at `packages/catalog-market-category-trees/tests/Feature/Tier3EndToEndTest.php`.
- Every test case is grouped `integration-destructive` (matches the project's Postgres test convention).
- Test passes when Postgres is available; skips gracefully when not (via `PostgresTestConnection::skipIfUnavailable()`).
- The deleteTree plugin assertion uses `CategoryTreeService` resolved from the container — proving the interceptor is wired in real boot.
- The container helper sets up `PluginInterceptor` + `PluginRegistry` + runs `PluginDiscovery::discoverInModule()` against the bridge manifest before any `CategoryTreeService` resolution.
- The bridge `ModuleManifest` is constructed with `path: dirname(__DIR__, 2)` (NOT empty/default).
- PHPStan + PHP-CS-Fixer clean.

## Implementation Notes

The concrete subclass plugin interception strategy (used when the plugin targets a concrete class directly) fails when the target class has required constructor arguments, because `PluginInterceptor` calls `new $generatedSubclass()` without args. To work around this without modifying the marko framework, `CategoryTreeServiceDeletePlugin` was changed to target `CategoryTreeServiceInterface` (a new contract interface extracted from `CategoryTreeService`). This triggers the interface wrapper strategy, which creates a standalone wrapper class that delegates to the real instance — no constructor inheritance issue.

Files created:
- `packages/catalog/src/Contracts/CategoryTreeServiceInterface.php` — new interface extracted from `CategoryTreeService`

Files modified:
- `packages/catalog/src/Services/CategoryTreeService.php` — implements `CategoryTreeServiceInterface`
- `packages/catalog-market-category-trees/src/Plugins/CategoryTreeServiceDeletePlugin.php` — targets `CategoryTreeServiceInterface` instead of `CategoryTreeService`
- `packages/catalog-market-category-trees/tests/Feature/Tier3EndToEndTest.php` — imports `PluginInterceptedInterface`, updates proxy assertion
- `packages/catalog-market-category-trees/tests/Unit/Plugins/CategoryTreeServiceDeletePluginTest.php` — updates plugin target assertion to `CategoryTreeServiceInterface`
