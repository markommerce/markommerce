# Task 008: Add ScopedProductGridComponent via #[Preference]

**Status**: completed
**Depends on**: 004, 007
**Retry count**: 0

## Description
Add `ScopedProductGridComponent` to `markommerce/catalog-scope`. It extends catalog's plain `ProductGridComponent`, injects `ScopeResolver`, and overrides `data()` to populate `resolvedNames` and `resolvedDescs` with values returned by `$scopeResolver->resolved($product, 'name'|'description')`. The class carries `#[Preference(replaces: ProductGridComponent::class)]` so Marko's container returns the scoped variant whenever any consumer asks for a `ProductGridComponent`.

The scope-aware test cases that were stripped from catalog's `ProductGridComponentTest` in task 004 land here.

## Context
- Related files (new):
  - `packages/catalog-scope/src/Component/ScopedProductGridComponent.php`
  - `packages/catalog-scope/tests/Unit/Component/ScopedProductGridComponentTest.php`
- Reference for the override pattern:
  - `Marko\Core\Attributes\Preference` in `../marko/packages/core/src/Attributes/Preference.php`
  - `PreferenceRegistry::register()` auto-resolves the chain via `PreferenceDiscovery`
  - `PreferenceDiscovery::discoverInModule()` (lines 22-68 of `marko/packages/core/src/Container/PreferenceDiscovery.php`) requires a `ModuleManifest` whose `path` points to the package source directory. The container-level discovery test in the requirements below must construct `new ModuleManifest(name: 'markommerce/catalog-scope', version: '1.0.0', path: dirname(__DIR__, 2))` and invoke `PreferenceDiscovery` + `PreferenceRegistry::register()` explicitly — Application bootstrapping is not available in package-level unit tests.
  - `marko/packages/core/tests/Unit/Container/PreferenceTest.php` shows the canonical wiring shape for a discovery-driven test.
- Shape:
  ```php
  #[Preference(replaces: ProductGridComponent::class)]
  class ScopedProductGridComponent extends ProductGridComponent
  {
      public function __construct(
          CategoryRepositoryInterface $categoryRepository,
          CategoryAssignmentService $categoryAssignmentService,
          private ScopeResolver $scopeResolver,
      ) {
          parent::__construct($categoryRepository, $categoryAssignmentService);
      }

      public function data(Category $category): ProductGridData
      {
          $data = parent::data($category);
          // walk $data->products, overwrite each entry in
          //   $data->resolvedNames / $data->resolvedDescs
          // with $this->scopeResolver->resolved($product, 'name'|'description')
          return $data;
      }
  }
  ```
- Pre-existing reference for what the resolved-values pattern looked like before P2:
  `packages/catalog/src/Component/ProductGridComponent.php` (pre-task-004 git history)
  `packages/catalog/tests/Unit/Component/ProductGridComponentTest.php` cases `resolves product names through ScopeResolver rather than the raw column value` (pre-task-004)

## Requirements (Test Descriptions)
- [x] `it carries the #[Preference(replaces: ProductGridComponent::class)] attribute`
- [x] `it extends Markommerce\\Catalog\\Component\\ProductGridComponent`
- [x] `it accepts CategoryRepositoryInterface, CategoryAssignmentService, and ScopeResolver in its constructor`
- [x] `it returns a ProductGridData populated by parent::data() then overwrites resolvedNames with values from ScopeResolver::resolved`
- [x] `it returns a ProductGridData with resolvedDescs overwritten from ScopeResolver::resolved`
- [x] `it falls back to the raw product name when ScopeResolver::resolved returns the raw value (no override set)`
- [x] `it returns a scoped value for resolvedNames when a locale override is set on the ProductScopedOverrides companion and the active locale context matches`
- [x] `it is resolved by the container as the preferred binding for ProductGridComponent when catalog-scope is installed (verifies #[Preference] discovery)`

## Acceptance Criteria
- All requirements have passing tests.
- `ScopedProductGridComponent` lives in `packages/catalog-scope/src/Component/`.
- A container-level test demonstrates that `$container->get(ProductGridComponent::class)` returns an instance of `ScopedProductGridComponent` once catalog-scope's preferences are discovered.
- No regression in `packages/catalog/tests/Unit/Component/ProductGridComponentTest.php`.
- Code follows project standards.

## Implementation Notes
- `ScopedProductGridComponent` lives in `packages/catalog-scope/src/Component/ScopedProductGridComponent.php`.
- Carries `#[Preference(replaces: ProductGridComponent::class)]` so Marko's container auto-resolves the scoped variant.
- Constructor accepts the same two params as parent plus `ScopeResolver $scopeResolver` as the third.
- `data()` calls `parent::data()`, then walks each product and overwrites `resolvedNames[$id]` and `resolvedDescs[$id]` via `$this->scopeResolver->resolved($product, 'name'|'description')`.
- Tests use real `ScopeResolver` infrastructure (not a fake subtype, since `ScopeResolver` is `readonly class` and cannot be subclassed by non-readonly classes).
- Tests register `Product::$name` and `Product::$description` as locale-scoped via `ScopedFieldRegistry` and attach `ProductScopedOverrides` companions for locale override assertions.
- Container discovery test wires up `PreferenceDiscovery` + `PreferenceRegistry` explicitly and provides concrete bindings for all `ScopedProductGridComponent` constructor dependencies.
- No regression in `packages/catalog/tests/Unit/Component/ProductGridComponentTest.php` — all 13 existing tests pass.
