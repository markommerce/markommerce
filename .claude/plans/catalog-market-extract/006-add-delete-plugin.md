# Task 006: Add `CategoryTreeServiceDeletePlugin` guard

**Status**: completed
**Depends on**: 004
**Retry count**: 0

## Description
Add a Marko Plugin that intercepts `Markommerce\Catalog\Services\CategoryTreeService::deleteTree` and throws `TreeHasMarketAssignmentsException` when the target tree still serves any market. This restores the friendly exception that catalog's `deleteTree` used to throw inline — except now the guard lives in the bridge, so it only fires when `catalog-market-category-trees` is installed.

## Context
- Marko Plugin reference: `packages/scope/src/Plugins/ScopeResolutionCommandPlugin.php` uses the same `#[Plugin(target: …)]` + `#[Before(method: …)]` pattern. Discovery is automatic — no module.php registration needed; the file just lives under the autoloaded `Plugins/` folder.
- Attribute imports: `Marko\Core\Attributes\Plugin` and `Marko\Core\Attributes\Before` (verified to exist in `marko/core`).
- Target signature: `Markommerce\Catalog\Services\CategoryTreeService::deleteTree(int $treeId): void`. The plugin's `beforeDeleteTree(int $treeId): void` must mirror it exactly.
- The plugin depends on the relocated `Markommerce\CatalogMarketCategoryTrees\Contracts\CategoryTreeMarketAssignmentRepositoryInterface` only — it does NOT touch `CategoryTreeRepositoryInterface` or `CategoryTreeService` itself.
- The exception construction mirrors the previous inline code:
  ```php
  $assignments = $this->categoryTreeMarketAssignmentRepository->findByTree($treeId);
  if (count($assignments) > 0) {
      $markets = array_map(fn ($a) => $a->market, $assignments);
      throw TreeHasMarketAssignmentsException::forTreeId($treeId, $markets);
  }
  ```
- Tests live at `packages/catalog-market-category-trees/tests/Unit/Plugins/CategoryTreeServiceDeletePluginTest.php`. The unit test exercises the plugin in isolation against `FakeCategoryTreeMarketAssignmentRepository` — no need to boot the interceptor here; that's the Tier 3 E2E test's job (task 009).

## Requirements (Test Descriptions)
- [ ] `it throws TreeHasMarketAssignmentsException when the tree has at least one market assignment`
- [ ] `it returns silently when the tree has no market assignments`
- [ ] `it lists every assigned market in the exception message in stable order`
- [ ] `it declares the Plugin attribute targeting Markommerce\\Catalog\\Services\\CategoryTreeService`
- [ ] `it declares the Before attribute targeting the deleteTree method`
- [ ] `it accepts the same int $treeId parameter that CategoryTreeService::deleteTree expects`

## Acceptance Criteria
- `packages/catalog-market-category-trees/src/Plugins/CategoryTreeServiceDeletePlugin.php` exists with the attribute set described above.
- Unit test file passes.
- The plugin class is `readonly` (matches the project convention seen in `ScopeResolutionCommandPlugin`).
- `@throws` PHPDoc tag on `beforeDeleteTree`.
- **This task does NOT edit `packages/catalog-market-category-trees/module.php`.** Plugins are auto-discovered by Marko's `PluginDiscovery` scanner from `src/Plugins/`; no explicit registration is required (verified pattern: `packages/scope/src/Plugins/ScopeResolutionCommandPlugin.php`). Task 004 owns module.php; this task only adds source files.
- PHPStan + PHP-CS-Fixer clean.

## Implementation Notes
- Marko's `#[Before]` plugin return-type convention:
  - `void` — continue to target method (used here).
  - `null` (nullable return type) — continue to target method.
  - `array` — replace the target method's arguments (positional).
  - any non-null scalar/object value — short-circuit and return that value as the target method's result.
  The guard here throws on failure and otherwise returns silently → `void` is correct. Reference: `marko/packages/core/tests/Unit/Plugin/PluginInterceptionTest.php` and `marko/packages/core/src/Plugin/PluginInterception.php` for the dispatch rules.
- The `beforeDeleteTree(int $treeId): void` signature must mirror the target's `deleteTree(int $treeId): void` parameter list exactly. If catalog ever adds an optional second parameter to `deleteTree`, the interceptor will silently misbehave — PHPStan level 8 will catch type drift, and the Tier 3 E2E test (task 009) catches behavioural drift.
