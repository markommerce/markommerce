# Task 002: Trim catalog repository / tree / seeder integration tests

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Several catalog repository/tree/seeder integration tests re-prove logic already covered by fake-backed unit tests (`FakeCategoryTreeNodeRepositoryTest`, `CategoryTreeService*` unit tests, `CatalogSeederTest`). Keep only the cases that exercise real SQL ordering, materialized-path round-trips, FK/placement guards, and partial-index behavior at the DB boundary.

## Context
- Files owned by THIS task (do not touch task 003's files): `RepositoryImplementationsTest.php`, `Repositories/CategoryTreeNodeRepositoryIntegrationTest.php`, `Repositories/CategoryTreeRepositoryIntegrationTest.php`, `Repositories/ProductCategoryAssignmentRepositoryIntegrationTest.php`, `CategoryTreeIntegrationTest.php`, `CatalogSeederTreeTest.php`. Leave (no edit) `Sorting/CategorySortOrderRegistryBootTest.php` and `Pricing/PriceContributorRegistryTest.php` — task 003 owns the other `Sorting/` and `Pricing/` files, so the shared dirs are still file-disjoint.
- Fake-backed equivalents (**paths verified — use these exact paths when confirming before deletion**):
  - `packages/catalog/tests/Unit/Repositories/FakeCategoryTreeNodeRepositoryTest.php` (NOT under `tests/Unit/Support/`).
  - Service twins: `packages/catalog/tests/Unit/Services/CategoryTreeServiceTreeCrudTest.php`, `CategoryTreeServiceMaterializationTest.php`, `CategoryTreeServicePlaceAndMoveTest.php`, `CategoryTreeServiceRemoveAndReorderTest.php`.
  - Cycle-detection twin: `packages/catalog/tests/Unit/Exceptions/CircularNodeReferenceExceptionTest.php` + `CategoryTreeServicePlaceAndMoveTest.php`.
  - Placement-guard twin: `packages/catalog/tests/Unit/Exceptions/CategoryHasPlacementsExceptionTest.php`.
  - Seeder twin: `packages/catalog/tests/Unit/Seed/CatalogSeederTest.php`.

## Requirements (verification assertions about the resulting suite)
- [ ] `it trims CategoryTreeNodeRepositoryIntegrationTest from seven to three` — keep persist-roundtrip, `findChildren` ordering, `findByCategoryInTree` (real WHERE+ORDER BY); delete find-all / find-roots / find-across-trees / delete (mirrored by the Fake repo unit test).
- [ ] `it trims CategoryTreeRepositoryIntegrationTest to the SQL-unique cases` — keep `findByCode` uniqueness + `findDefault`/`DefaultTreeMissingException` (partial-index behavior); delete plain persist-roundtrip and plain delete CRUD smoke tests.
- [ ] `it keeps ProductCategoryAssignmentRepositoryIntegrationTest as-is` — single position round-trip is the assignment-table smoke test; leave intact.
- [ ] `it trims CategoryTreeIntegrationTest from four to one` — keep the full materialized nested-tree assertion; delete multi-placement (twin: `CategoryTreeServicePlaceAndMoveTest`), `CategoryHasPlacementsException` (twin: `Unit/Exceptions/CategoryHasPlacementsExceptionTest` + `CategoryTreeServiceRemoveAndReorderTest`), and cycle-detection (twin: `Unit/Exceptions/CircularNodeReferenceExceptionTest` + `CategoryTreeServicePlaceAndMoveTest`). **Open each named twin and confirm it asserts the same exception/behavior before deleting; if a twin only constructs the exception (a thin exception-shape test) and does not exercise the service path that raises it, keep the integration case and note it.**
- [ ] `it trims CatalogSeederTreeTest from five to one` — keep one "seeder places every category as a root node"; delete idempotency/dedup/position-order (re-prove `CatalogSeederTest` + place-and-move unit logic).
- [ ] `it deletes RepositoryImplementationsTest tautologies` — this file is misfiled (no DB, fake `ConnectionInterface`, not in integration group): delete the entity-class-declaration assertions and the brittle "SQL contains table name" assertions; if nothing of value remains, delete the file.
- [ ] `it keeps CategorySortOrderRegistryBootTest and PriceContributorRegistryTest` — genuine no-DB module-boot wiring; leave intact.

## Acceptance Criteria
- `./vendor/bin/pest packages/catalog/tests/Feature/Repositories packages/catalog/tests/Feature/CategoryTreeIntegrationTest.php packages/catalog/tests/Feature/CatalogSeederTreeTest.php` green with DB.
- Every deleted case's named unit equivalent verified to exist; otherwise kept and noted.
- No orphaned support files / dangling references.

## Execution (deletion task — no Red phase)
1. `./vendor/bin/pest packages/catalog/tests/Feature/Repositories packages/catalog/tests/Feature/CategoryTreeIntegrationTest.php packages/catalog/tests/Feature/CatalogSeederTreeTest.php packages/catalog/tests/Feature/RepositoryImplementationsTest.php` with DB → confirm green baseline.
2. Apply deletions; for each, open the named twin and confirm equivalence first (see Context paths).
3. Re-run the same suite green; `./vendor/bin/phpcs` on touched files.

## Implementation Notes
(Left blank — filled in during implementation.)
