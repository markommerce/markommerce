# Task 003: Trim catalog service / factory / sort integration tests

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
`CategoryAssignmentServicePaginatedIntegrationTest` (732 lines) mostly re-proves `OffsetPaginationStrategyTest` against Postgres, where the DB adds no signal. `FixtureFactoriesTest` tests the test factories themselves. Trim both to the cases that carry real SQL/cross-module risk, and move one no-DB loud-exception case into its unit home.

## Context
- Files owned by THIS task (file-disjoint from task 002, though both touch the `Sorting/` and `Pricing/` dirs — never the same file):
  - `packages/catalog/tests/Feature/Services/CategoryAssignmentServicePaginatedIntegrationTest.php`
  - `packages/catalog/tests/Feature/Factories/FixtureFactoriesTest.php`
  - `packages/catalog/tests/Feature/Sorting/EndToEndPositionSortOrderIntegrationTest.php`
  - `packages/catalog/tests/Feature/Pricing/PriceResolverTest.php`
- Unit equivalents: `packages/criteria/tests/Unit/Strategy/OffsetPaginationStrategyTest.php`, `packages/catalog/tests/Unit/Pagination/PaginationOptionsResolverTest.php`, `packages/catalog/tests/Unit/Pricing/BatchPriceResolverTest.php`.

## Requirements (verification assertions about the resulting suite)
- [ ] `it collapses CategoryAssignmentServicePaginatedIntegrationTest to the SQL-unique cases` — keep the real join `ORDER BY` (assignment position default + configured column with id tie-break) and the join-safe `COUNT` (offset total not double-counting joined rows); delete the ~9 cases re-proving offset math / `size+1` hasNext / offset-token encoding / `prepareCallCount` spy / basic happy path, and downgrade the `CategoryNotFoundException` case (covered by `CategoryAssignmentServiceTest` with a fake).
- [ ] `it prunes FixtureFactoriesTest to cross-module wiring only` — keep at most "writes an indexed price row when the profile supports it" + "fails clearly when indexed price requested without the price-index module"; delete the factory-works assertions (implicitly exercised by every test that uses the factory) and the composer.json require-dev lint.
- [ ] `it deletes the redundant keyset loud-exception case from EndToEndPositionSortOrderIntegrationTest` — that case (lines ~164-185, no DB) is **already covered verbatim** by `PaginationOptionsResolverTest` case `it throws a loud keyset-incompatibility exception when a non-keyset order is requested under the keyset strategy` (verified to exist, line ~155, same exception + message/context/suggestion shape). Therefore **delete** the integration case outright — do NOT "fold/move" it (that would create a duplicate). Keep the resolve→apply default-position end-to-end DB case (lines ~112-162). After deletion, the helpers `e2ePositionMakeRegistry()`/`e2ePositionMakeResolver()`/`e2ePositionMakeServiceFromConn()` are still used by the kept DB case — leave them. If a future edit removes the last user of any of those same-file helpers, remove the helper too.
- [ ] `it drops the single-vs-batch parity case from PriceResolverTest` — duplicates `BatchPriceResolverTest`; keep the boot-binding + resolve DB cases.
- [ ] `it keeps every surviving DB case tagged and skip-guarded`.

## Acceptance Criteria
- `./vendor/bin/pest packages/catalog/tests/Feature/Services packages/catalog/tests/Feature/Factories packages/catalog/tests/Feature/Sorting packages/catalog/tests/Feature/Pricing` green with DB.
- `OffsetPaginationStrategyTest` confirmed to cover each deleted pagination case.
- Spy/count helper boilerplate removed with its tests; no dangling references.

## Execution (deletion task — no Red phase)
1. Run the targeted suite green first (see Acceptance Criteria command).
2. For each deleted pagination case in `CategoryAssignmentServicePaginatedIntegrationTest`, confirm `packages/criteria/tests/Unit/Strategy/OffsetPaginationStrategyTest.php` asserts the equivalent offset math before deleting; keep+note any case whose equivalent is absent.
3. Remove spy/count-helper boilerplate together with the tests that use it (grep for the helper name to confirm no other user remains in the file).
4. Re-run suite green; phpcs on touched files.

## Implementation Notes
(Left blank — filled in during implementation.)
