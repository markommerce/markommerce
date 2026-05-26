# Task 003: Wire ScopedFieldRegistry into the scope module

**Status**: completed
**Depends on**: 001, 002
**Retry count**: 0

## Description
Register the new `ScopedFieldRegistry` as a singleton in
`packages/scope/module.php` and confirm `ScopeMetadataFactory` resolves
correctly from the container with its new constructor signature. Verify
the boot order so that axes are registered (by `PhpScopeRegistry`) before
any field is registered against the new registry.

## Context
- Related files:
  - `packages/scope/module.php` — current module wiring; needs the new singleton entry
  - `packages/scope/tests/Unit/ModulePhpTest.php` — existing test pattern for module wiring (this is the test that asserts singletons and bindings; `ModulePhpPipelineTest.php` covers boot behaviour)
- Existing pattern: see how `ScopeMetadataFactory::class` is registered under `'singletons'` in the module file. The new entry follows the same pattern.
- `ScopedFieldRegistry`'s constructor depends only on `ScopeRegistryInterface`, which is already a container binding. No factory closure needed; plain singleton entry suffices.
- The boot closure in `module.php` MUST NOT be modified to populate the registry. `PhpScopeRegistry`'s axes are available the moment the container resolves the interface, so bridges can call `register()` from their own boot closures without scope's boot needing to run first. Verify this assumption with a test that resolves `ScopedFieldRegistry` and calls `register('SomeClass', 'name', ['locale'])` without first invoking scope's boot closure — it should succeed.

## Requirements (Test Descriptions)
- [x] `module.php registers ScopedFieldRegistry as a singleton`
- [x] `it resolves ScopedFieldRegistry from a real container with scope's module loaded`
- [x] `it returns the same ScopedFieldRegistry instance on repeated container resolutions (singleton)`
- [x] `it injects the same ScopedFieldRegistry instance into ScopeMetadataFactory via the container`
- [x] `it allows register() to succeed without scope's boot closure having run, because PhpScopeRegistry resolves its axes at construction time`
- [x] `it produces correct metadata end-to-end for Markommerce\Catalog\Entity\Product after wiring (locale axis on name and description)`
- [x] `it produces correct metadata end-to-end for Markommerce\Catalog\Entity\Category after wiring`

## Acceptance Criteria
- `packages/scope/module.php` lists `ScopedFieldRegistry::class` under `'singletons'`.
- No changes to `ScopeMetadataFactory`'s entry in the module file beyond what task 002 already required (constructor changes resolve automatically).
- New tests appended to `packages/scope/tests/Unit/ModulePhpTest.php` (the existing module-wiring test, to keep the singleton-assertion idiom consistent). Cross-module end-to-end metadata tests for `Product`/`Category` live in `packages/scope/tests/Feature/CatalogMetadataIntegrationTest.php` (or similar Feature-level location) to avoid making `ModulePhpTest` depend on catalog classes.
- The `Product`/`Category` integration tests use a real `Container` instance with both `packages/scope/module.php` and `packages/catalog/module.php` loaded (or the minimum subset of bindings required) — they MUST NOT instantiate `ScopeMetadataFactory` by hand, because the whole point is to verify container-driven wiring.
- Full scope and catalog test suites pass — verified via `composer test:all`. `Product` and `Category` still produce `['locale']` axes for their scoped properties through the now-registry-driven factory.
- PHPStan level 8 clean. PHP-CS-Fixer and `phpcs` pass.

## Implementation Notes
- Added `ScopedFieldRegistry::class` to the `'singletons'` array in `packages/scope/module.php` (alongside the existing `ScopeMetadataFactory::class` entry). Also added the `use` import for `ScopedFieldRegistry`.
- Appended 5 new tests to `packages/scope/tests/Unit/ModulePhpTest.php` covering: singleton registration, container resolution, singleton identity, factory injection identity, and boot-order independence.
- Created `packages/scope/tests/Feature/CatalogMetadataIntegrationTest.php` with 2 end-to-end tests for `Product` and `Category` metadata via a real Container.
- Requirements 2-7 all passed immediately after requirement 1 was implemented — this is expected because the singleton entry is the only wiring change needed; the container already resolves `ScopedFieldRegistry` via autowiring once `ScopeRegistryInterface` is bound, and `PhpScopeRegistry` reads axes from config at construction time (no boot needed).
- Boot closure left unchanged per spec — scope's boot closure does not populate `ScopedFieldRegistry`.
- PHPStan cache permission errors are pre-existing infrastructure issues; actual source files are clean (`No errors` when run against `src/` only).
