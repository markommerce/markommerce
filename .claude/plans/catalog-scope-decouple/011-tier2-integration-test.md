# Task 011: Tier 2 end-to-end integration test

**Status**: completed
**Depends on**: 002, 007, 008, 009
**Retry count**: 0

## Description
Add a feature-level integration test in `markommerce/catalog-scope` (or in `markommerce/catalog-locale` if that fits better; the bridge stack as a whole is being tested) that boots a real container with the module manifests for: `scope`, `catalog`, `catalog-scope`, `locale`, and `catalog-locale`, then walks through the full Tier 2 happy path. The test uses an **in-memory connection** for `Repository` interactions (mirroring `ScopedOverridesPersistenceTest`'s logging-connection pattern) so it stays in the fast `composer test` suite — there is no need to install `scope-pgsql` because resolution is in-memory via `HasScopes` storage on the companion.

1. The `locale` axis is present in `ScopeRegistryInterface` after boot.
2. `ScopedFieldRegistry::propertiesFor(Product::class)` returns `['name' => ['locale'], 'description' => ['locale']]`.
3. Same for `Category::class`.
4. A `Product` is saved with `name = 'Shirt'`. A `ProductScopedOverrides` companion is attached with an override `name = 'Hemd'` at `locale:de`. The product persists and re-fetches with both states intact.
5. With `ScopeContext` in `('locale', 'default')`, `$scopeResolver->resolved($product, 'name')` returns `'Shirt'`.
6. With `ScopeContext` in `('locale', 'de')`, the resolver returns `'Hemd'`.
7. With `ScopeContext` in an unknown locale `('locale', 'fr')`, the resolver falls back to the raw value `'Shirt'`.

This test serves as the canonical Tier 2 contract for future phases — if it ever breaks, P3/P4 changes are touching something they shouldn't.

## Context
- Suggested location: `packages/catalog-scope/tests/Feature/Tier2EndToEndTest.php`
- References:
  - `packages/scope/tests/Feature/BridgeContributionTest.php` for container + boot-loop scaffolding
  - `packages/scope/tests/Feature/ScopedOverridesPersistenceTest.php` for the persistence round-trip mechanics
  - `packages/scope/tests/Feature/DefaultScopeResolutionTest.php` for context-switching + resolution assertions
- Architectural note: the test should NOT mock any of the production wiring — it boots the real `module.php` files from `scope`, `catalog`, `catalog-scope`, `locale`, and `catalog-locale`, ordered via `DependencyResolver`. Module manifests are constructed inline (à la `BridgeContributionTest`) so the test does not depend on `frontend-demo` (task 010) or any other consumer.

## Requirements (Test Descriptions)
- [x] `it registers the locale axis in ScopeRegistryInterface after booting all of scope + locale + catalog + catalog-scope + catalog-locale`
- [x] `it exposes Product.name as locale-scoped via ScopedFieldRegistry after the catalog-locale bridge boot runs`
- [x] `it exposes Product.description, Category.name, Category.description as locale-scoped after boot`
- [x] `it persists a Product with an attached ProductScopedOverrides companion and re-fetches both rows through ProductRepository::find`
- [x] `it returns the raw Product.name when the active locale context matches the axis default`
- [x] `it returns the German override Product.name when the active locale context is de`
- [x] `it falls back to the raw Product.name when the active locale context is an axis-valid scope with no override (e.g., fr)`
- [x] `it propagates the same resolution semantics for Category.name through CategoryRepository + CategoryScopedOverrides`

## Acceptance Criteria
- All requirements have passing tests.
- The integration test boots the real production wiring (no mocks of scope/catalog services).
- The test is repeatable and runs in the default `composer test` (parallel) suite.
- Code follows project standards.

## Implementation Notes
- Test file: `packages/catalog-scope/tests/Feature/Tier2EndToEndTest.php`
- The locale config is extended in-test to include `de` and `fr` scopes so `ScopeContext::in('locale', 'de')` and `in('locale', 'fr')` pass hierarchy validation.
- The scope module boot closure requires `ContainerInterface` — the Container instance is registered as `instance(ContainerInterface::class, $container)` before the boot loop runs.
- Catalog and catalog-scope manifests have no boot closures; only scope and catalog-locale contribute boot-time behavior.
- In-memory round-trip connections capture `insertedScopes` from the INSERT binding and replay it on SELECT, matching the pattern in `CompanionPersistenceTest`.
- All 8 tests pass; full parallel suite stays green (1488 passed).
