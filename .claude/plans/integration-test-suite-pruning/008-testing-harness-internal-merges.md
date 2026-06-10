# Task 008: Minor internal merges in the packages/testing harness suite

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
The `packages/testing` harness suite is healthy and largely kept. Only a few internal near-duplicates exist where the same boot/provision path is asserted in more than one place. Apply conservative merges only; when in doubt, keep.

## Context
- Files: `packages/testing/tests/Feature/Migration/MigrationRoundTripTest.php`, `Integration/IntegrationTestCaseTest.php`, `Profile/BootedStoreTest.php`, `Http/RequestDispatcherTest.php`, `InvariantMatrixTest.php`, `Schema/SchemaProvisionerTest.php`.
- This task is low-priority and intentionally light — do not weaken harness coverage.

## Requirements (verification assertions about the resulting suite)
- [ ] `it merges the two near-duplicate MigrationRoundTrip cases` — fold "applies the entity schema" into "creates the expected tables" (same provision + overlapping assertions).
- [ ] `it merges the standalone IntegrationTestCase boot case into the dataset case` — the per-profile dataset test already covers single-profile boot+rollback.
- [ ] `it drops the InvariantMatrix simple-profile subset case` — "does not expose price sort orders in the simple profile" is a strict subset of the matrix "registers ... only when present" case.
- [ ] `it leaves DatabaseProvisionerTest, TestConnectionTest, TestIsolationTest intact` — highest-risk harness behavior; no changes.

## Acceptance Criteria
- `./vendor/bin/pest --group=integration-destructive packages/testing` green with DB; `composer test` green without.
- No reduction in coverage of provisioning / isolation / schema introspection behavior.
- If any merge is ambiguous, keep both and note it.

## Execution (conservative-merge task — no Red phase)
1. Run `./vendor/bin/pest --group=integration-destructive packages/testing` (DB) + `composer test` green first.
2. For each merge, fold the unique assertions of the absorbed case into the survivor and re-run; if any assertion does not have an obvious home in the survivor, keep both and note it (this task errs toward keeping).
3. Re-run both suites green; phpcs on touched files.

## Implementation Notes
(Left blank — filled in during implementation.)
