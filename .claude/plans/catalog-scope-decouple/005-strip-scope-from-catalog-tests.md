# Task 005: Remove remaining scope coupling from catalog tests

**Status**: completed
**Depends on**: 003, 004
**Retry count**: 0

## Description
Strip all `Markommerce\Scope\...` imports, `DefaultScopeGuard::configure()` setup, and `ScopeResolver`/`ScopeContext`/`ScopeMetadataFactory` plumbing from catalog's feature tests and remaining unit tests. After this task, catalog can be run as a standalone package with no scope dependency, and `composer test` against catalog alone passes.

For tests that legitimately need scope-aware behaviour (e.g., a `CategoryLayoutTest` case that asserts a locale-scoped product name renders correctly), the case is either:
1. Reduced to its non-scope-aware form (assertion on raw values), or
2. Moved out of catalog and re-created in catalog-scope's test suite under task 008 / 011.

## Context
- Affected files (catalog tests):
  - `packages/catalog/tests/Feature/CategoryControllerTest.php` — remove `catalogControllerBuildScopeResolver()` helper, all scope imports, the `$container->instance(ScopeResolver::class, ...)` line, and any cases that exercise locale-scoped values. The Latte view-rendering tests must still build `ProductGridComponent` with its post-task-004 two-arg signature.
  - `packages/catalog/tests/Feature/CategoryLayoutTest.php` — same pattern; helper is `catalogLayoutBuildScopeResolver()`. Note line 265 and 359 currently instantiate `ProductGridComponent(..., ..., $scopeResolver)` with three args; after task 004 this becomes two args. Remove the `$scopeResolver` parameter from those construction calls.
  - `packages/catalog/tests/Feature/CategoryTreeIntegrationTest.php` — uses `DefaultScopeGuard::configure()` for setup; remove. **Also strip the inline `scopes JSON` column from the `CREATE TABLE catalog_categories` statement at lines 105-111** — that column is owned by `catalog-scope`'s `CategoryScopedOverrides` companion post-P2 and is no longer part of catalog's owned schema.
  - `packages/catalog/tests/Feature/CatalogSeederTreeTest.php` — same scope-guard removal pattern; also remove any DB schema setup that references a `scopes` column on catalog tables.
  - `packages/catalog/tests/Unit/Seed/CatalogSeederTest.php` — `DefaultScopeGuard::configure()` setup already removed in task 003; this task verifies no further scope references remain.
- Patterns to follow: existing catalog test scaffolding without scope; the entity tests under `tests/Unit/Entity/` already show the shape after task 003.

## Requirements (Test Descriptions)
- [x] `it has no Markommerce\\Scope imports in any catalog test file under tests/Feature`
- [x] `it has no Markommerce\\Scope imports in any catalog test file under tests/Unit (other than relocations to catalog-scope)`
- [x] `it has no scopes column in the inline CREATE TABLE catalog_categories statement in CategoryTreeIntegrationTest`
- [x] `it has no scopes column in any inline CREATE TABLE catalog_products statement in catalog test setup (if any exists)`
- [x] `it runs the catalog test suite to green with markommerce/scope NOT installed (simulated via composer.json absence in task 006)`
- [x] `it preserves all non-scope test cases in CategoryControllerTest (controller wiring, route resolution, response shape)`
- [x] `it preserves all non-scope test cases in CategoryLayoutTest (layout rendering, theme integration, raw product name display)`
- [x] `it preserves all non-scope test cases in CategoryTreeIntegrationTest (tree CRUD, market assignment lifecycle)`
- [x] `it preserves all non-scope test cases in CatalogSeederTreeTest and CatalogSeederTest (seeder happy paths)`

## Acceptance Criteria
- All requirements have passing tests.
- `grep -rn "Markommerce.Scope" packages/catalog/tests` returns zero matches.
- `grep -rn "Markommerce.Scope" packages/catalog/src packages/catalog/Seed` returns zero matches (validates task 003 + this task end-to-end).
- `grep -rn "DefaultScopeGuard" packages/catalog/tests` returns zero matches.
- `grep -rn "ScopeResolver" packages/catalog/tests` returns zero matches.
- `grep -rn "scopes JSON\|scopes JSONB\|scopes JsonB" packages/catalog/tests` returns zero matches.
- The catalog test suite passes (`./vendor/bin/pest packages/catalog/tests`).
- Code follows project standards.

## Implementation Notes
- Removed all `Markommerce\Scope\*` imports from `CategoryControllerTest.php`, `CategoryLayoutTest.php`, `CategoryTreeIntegrationTest.php`, and `CatalogSeederTreeTest.php`.
- Removed `catalogControllerBuildScopeResolver()` and `catalogLayoutBuildScopeResolver()` helper functions.
- Updated `ProductGridComponent` construction calls from 3-arg to 2-arg form (post task-004 signature).
- Removed `$container->instance(ScopeResolver::class, ...)` bindings from both controller and layout tests.
- Stripped `scopes JSON` column from `CREATE TABLE catalog_categories` in `CategoryTreeIntegrationTest.php` and `CatalogSeederTreeTest.php`.
- Stripped `scopes JSON` column from `CREATE TABLE catalog_products` in `CatalogSeederTreeTest.php`.
- Removed `DefaultScopeGuard::configure()` / `::reset()` calls from `beforeEach`/`afterEach` in both integration test files.
- Updated Unit entity tests (`CategoryTest`, `ProductTest`, `ProductCategoryAssignmentTest`) to check scope absence via string-based reflection instead of importing scope interfaces.
- Removed `markommerce/scope` from `packages/catalog/composer.json` `require` section.
- Added `packages/catalog/tests/Unit/ScopeDecouplingTest.php` with 9 new tests verifying all acceptance criteria.
