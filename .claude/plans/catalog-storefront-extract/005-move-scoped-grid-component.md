# Task 005: Move ScopedProductGridComponent from catalog-scope to catalog-storefront-scope

**Status**: completed
**Depends on**: 002, 004
**Retry count**: 0

## Description
Relocate `ScopedProductGridComponent` and its test from `markommerce/catalog-scope` to `markommerce/catalog-storefront-scope`. After this task:
- `packages/catalog-scope/src/Component/ScopedProductGridComponent.php` no longer exists.
- `packages/catalog-scope/tests/Unit/Component/ScopedProductGridComponentTest.php` no longer exists.
- `packages/catalog-storefront-scope/src/Component/ScopedProductGridComponent.php` exists with namespace `Markommerce\CatalogStorefrontScope\Component\` and imports from both `Markommerce\CatalogStorefront\…` (for `ProductGridComponent` and `ProductGridData`) and `Markommerce\Scope\…` (for `ScopeResolver` and the exceptions).
- `packages/catalog-storefront-scope/tests/Unit/Component/ScopedProductGridComponentTest.php` exists with updated imports and updated `ModuleManifest` path/name (`'markommerce/catalog-storefront-scope'` for the discovery test).
- `packages/catalog-scope/composer.json` no longer requires `markommerce/catalog-storefront` (added temporarily in task 002, removed here now that the only file needing it has moved out).

Catalog-scope is left clean: it owns only the scoped-overrides storage entities, the locale seeder, and the entity-level companion machinery. Catalog-storefront-scope owns the scope-aware storefront rendering.

## Context
- Source file to move: `packages/catalog-scope/src/Component/ScopedProductGridComponent.php` (created in P2 task 008).
- Test file to move: `packages/catalog-scope/tests/Unit/Component/ScopedProductGridComponentTest.php`.
- Target locations:
  - `packages/catalog-storefront-scope/src/Component/ScopedProductGridComponent.php`
  - `packages/catalog-storefront-scope/tests/Unit/Component/ScopedProductGridComponentTest.php`
- Namespace changes:
  - `namespace Markommerce\CatalogScope\Component;` → `namespace Markommerce\CatalogStorefrontScope\Component;`
  - Test file `dirname(__DIR__, 3)` already resolves to the package root after the move — verify and adjust if needed.
- Composer changes:
  - `packages/catalog-scope/composer.json`: remove `markommerce/catalog-storefront` from `require` (added temporarily in task 002).
  - `packages/catalog-storefront-scope/composer.json`: already requires both `markommerce/catalog-scope` and `markommerce/catalog-storefront` (task 004).
- `PreferenceDiscovery` discovery test inside the moved test file (currently lines 273–325) constructs a `ModuleManifest(name: 'markommerce/catalog-scope', path: dirname(__DIR__, 3))`. After the move, this becomes `name: 'markommerce/catalog-storefront-scope', path: dirname(__DIR__, 3)` (resolving to `packages/catalog-storefront-scope/`).
- Sweep for residual references:
  - `grep -rn "ScopedProductGridComponent" packages/catalog-scope` → must return zero after the move.
  - `grep -rn "Markommerce\\\\CatalogScope\\\\Component" packages/` → must return zero.
- The Tier 2 end-to-end test in `packages/catalog-scope/tests/Feature/Tier2EndToEndTest.php` does NOT reference `ScopedProductGridComponent` directly (it tests the scope resolution path, not the grid component). Verify with a grep before assuming.

## Requirements (Test Descriptions)
- [ ] `it relocates packages/catalog-scope/src/Component/ScopedProductGridComponent.php to packages/catalog-storefront-scope/src/Component/ScopedProductGridComponent.php with namespace Markommerce\\CatalogStorefrontScope\\Component`
- [ ] `it relocates the matching test file to packages/catalog-storefront-scope/tests/Unit/Component/ScopedProductGridComponentTest.php with updated imports`
- [ ] `it updates the PreferenceDiscovery test inside the moved test to construct a ModuleManifest with name=markommerce/catalog-storefront-scope and the corresponding path`
- [ ] `it removes markommerce/catalog-storefront from packages/catalog-scope/composer.json require (added temporarily in task 002)`
- [ ] `it asserts catalog-scope's composer.json no longer lists markommerce/catalog-storefront in require or require-dev (regression guard)`
- [ ] `it asserts no file under packages/catalog-scope references Markommerce\\CatalogStorefront\\ or Markommerce\\CatalogStorefrontScope\\ after the move`
- [ ] `it asserts no Markommerce\\CatalogScope\\Component namespace remains in packages/ after the move`
- [ ] `the catalog-storefront-scope test suite passes (Preference attribute, extension check, and resolver-overwrite assertions)`
- [ ] `the catalog-scope Tier 2 end-to-end test still passes after ScopedProductGridComponent leaves the package`

## Acceptance Criteria
- All requirements have passing tests.
- catalog-scope, catalog-storefront, and catalog-storefront-scope test suites all stay green.
- The `#[Preference(replaces: ProductGridComponent::class)]` attribute on the moved class still triggers the Container preference swap when the Tier 2 storefront stack is booted.
- Code follows project standards.

## Implementation Notes
(Left blank — filled in by programmer during implementation.)
