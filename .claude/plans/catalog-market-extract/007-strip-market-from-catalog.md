# Task 007: Strip market features from `catalog`

**Status**: completed
**Depends on**: 005, 006
**Retry count**: 0

## Description
Remove all remaining market knowledge from `markommerce/catalog`. Drop the three market methods + the market repo dep from `CategoryTreeService`, drop the binding from `module.php`, delete the now-obsolete market test file, prune market cases from the surviving test files, drop the market-assignments table from the integration test's setup, and clean up the README. After this task, catalog's full test suite is green and contains no reference to any of the relocated symbols.

## Context
- Edits to `packages/catalog/src/Services/CategoryTreeService.php`:
  - Drop constructor parameter `CategoryTreeMarketAssignmentRepositoryInterface $categoryTreeMarketAssignmentRepository`.
  - Delete methods `assignTreeToMarket`, `unassignMarket`, `resolveTreeForMarket`.
  - In `deleteTree`, remove the `findByTree` + `TreeHasMarketAssignmentsException` block; keep the `CannotDeleteDefaultTreeException` and `CategoryTreeNotFoundException` paths.
  - **Update `deleteTree`'s `@throws` PHPDoc tag**: drop `TreeHasMarketAssignmentsException` from the union. Current: `@throws CategoryTreeNotFoundException|CannotDeleteDefaultTreeException|TreeHasMarketAssignmentsException`. New: `@throws CategoryTreeNotFoundException|CannotDeleteDefaultTreeException`. PHPStan level 8 fails otherwise once the import is removed.
  - Delete the now-unused imports: `CategoryTreeMarketAssignmentRepositoryInterface`, `CategoryTreeMarketAssignment` entity, `TreeHasMarketAssignmentsException`.
- Edits to `packages/catalog/module.php`:
  - Drop the `CategoryTreeMarketAssignmentRepositoryInterface::class => CategoryTreeMarketAssignmentRepository::class` binding and its imports.
- Edits to `packages/catalog/tests/`:
  - Delete `tests/Unit/Services/CategoryTreeServiceMarketResolutionTest.php` (relocated in task 005).
  - In `tests/Unit/Services/CategoryTreeServiceTreeCrudTest.php`:
    - Drop the `deleteTree throws TreeHasMarketAssignmentsException when the tree still serves any market` case.
    - Drop the `FakeCategoryTreeMarketAssignmentRepository` import and the `$assignmentRepo` parameter from `makeCategoryTreeService`; update the catalog service constructor call to the new three-arg signature.
  - In `tests/Feature/CategoryTreeIntegrationTest.php`:
    - Drop test cases: `creates a non-default tree, places categories, assigns it to a market, and resolves the tree for that market` and `resolves the default tree for a market with no assignment`. Other cases stay.
    - Drop the `CategoryTreeMarketAssignmentRepository` import and its instantiation in `makeServices`; update the `CategoryTreeService` constructor call.
    - Drop the `CREATE TABLE catalog_category_tree_market_assignments` block from `beforeEach` and the matching `DROP TABLE` from `beforeEach`/`afterEach`.
  - In `tests/Unit/ModuleBindingsTest.php`: drop the assertion that catalog binds `CategoryTreeMarketAssignmentRepositoryInterface`.
  - In `tests/Unit/ScopeDecouplingTest.php`: update the `preserves all non-scope test cases in CategoryTreeIntegrationTest` assertion to drop the two relocated market case literals. **Keep at least the `'materializes the tree with correct nesting and position order against the real database'` literal** so the assertion still serves its named purpose (verifying that the non-scope test cases survive). Optionally rename the test to `preserves non-scope, non-market test cases in CategoryTreeIntegrationTest` for accuracy now that two contracts (no scope, no market) hold.
- Edits to `packages/catalog/README.md`:
  - Strip the "per-market category trees" phrase from the opening blurb.
  - Strip the `$activeTree = $categoryTreeService->resolveTreeForMarket('market:eu')` example.
  - Cross-link to `markommerce/catalog-market-category-trees`.
- Edits to `packages/catalog/tests/Unit/ReadmeTest.php`: update any literal-content assertion that referenced the removed `resolveTreeForMarket` example.

## Requirements (Test Descriptions)
- [x] `CategoryTreeService no longer exposes assignTreeToMarket, unassignMarket, or resolveTreeForMarket public methods`
- [x] `CategoryTreeService no longer requires a CategoryTreeMarketAssignmentRepositoryInterface in its constructor signature`
- [x] `CategoryTreeService::deleteTree no longer throws TreeHasMarketAssignmentsException directly`
- [x] `CategoryTreeService::deleteTree's @throws PHPDoc tag lists only CategoryTreeNotFoundException and CannotDeleteDefaultTreeException`
- [x] `catalog module.php no longer binds CategoryTreeMarketAssignmentRepositoryInterface to any concrete repository`
- [x] `packages/catalog/tests/Unit/Services/CategoryTreeServiceMarketResolutionTest.php no longer exists`
- [x] `CategoryTreeServiceTreeCrudTest constructs CategoryTreeService with the new three-argument signature and no longer references FakeCategoryTreeMarketAssignmentRepository`
- [x] `CategoryTreeIntegrationTest no longer creates the catalog_category_tree_market_assignments table or instantiates CategoryTreeMarketAssignmentRepository`
- [x] `ScopeDecouplingTest's preserves all non-scope test cases in CategoryTreeIntegrationTest assertion is updated to drop the two relocated market literals but keep at least the materialization case literal so the assertion remains meaningful`
- [x] `the catalog test suite (composer test from packages/catalog) passes without the three new packages installed`
- [x] `packages/catalog/README.md no longer contains the substring resolveTreeForMarket`

## Acceptance Criteria
- All listed edits applied.
- `composer test` in `packages/catalog/` passes.
- `composer test:all` in `packages/catalog/` (including destructive Postgres integration) passes.
- `composer test:all` from the monorepo root passes (catches transitive demo or root-test breakage). If any demo package (`frontend-demo`, `theme-blank-demo`, `layout-demo`) breaks due to deleted symbols, escalate to the user — the demos are documented as having no market coupling, so a break here is a sign of an undocumented dependency rather than a thing to patch over.
- PHPStan level 8 and PHP-CS-Fixer clean for catalog.
- No file under `packages/catalog/src/` references `CategoryTreeMarketAssignment`, `TreeHasMarketAssignmentsException`, `assignTreeToMarket`, `resolveTreeForMarket`, `unassignMarket`, or `Markommerce\\CatalogMarketCategoryTrees\\`.

## Implementation Notes

All edits applied successfully. The full test suite (1611 tests) passes. Additional files updated beyond the task description:
- `packages/catalog/tests/Unit/Services/CategoryTreeServicePlaceAndMoveTest.php` — removed `FakeCategoryTreeMarketAssignmentRepository` import and `$assignmentRepo` param from `makeCategoryTreeServiceForPlaceAndMove`
- `packages/catalog/tests/Unit/Services/CategoryTreeServiceMaterializationTest.php` — same pattern
- `packages/catalog/tests/Unit/Services/CategoryTreeServiceRemoveAndReorderTest.php` — same pattern
- `packages/catalog/tests/Unit/Seed/CatalogSeederTest.php` — same pattern
- `packages/catalog-market-category-trees/README.md` — created (was missing; required by monorepo `FilePresenceTest`)
