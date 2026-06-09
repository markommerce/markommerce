# Task 009: `IntegrationTestCase` + Pest integration + profile dataset

**Status**: completed
**Depends on**: 007, 008, 016
**Retry count**: 0

## Description
Tie the pieces into the developer-facing test entry point: a base `IntegrationTestCase` (and/or Pest helper functions) that, given a chosen `StoreProfile`, provisions/clones the worker DB, boots the store, opens the isolation transaction in setup and rolls back in teardown, and exposes the booted store to the test. Provide a `storeProfiles` Pest dataset for the invariant matrix.

## Context
- Live in `packages/testing/src/` (e.g. `IntegrationTestCase.php`) + a Pest bootstrap snippet packages can include from their `tests/Pest.php` (e.g. `uses(IntegrationTestCase::class)` or helper functions `store(StoreProfile $p): BootedStore`).
- Lifecycle wiring (orchestrates 007 + 008 + 016):
  - Resolve the chosen profile → get-or-build its template → get-or-clone the worker DB (task 008) → boot the store against that DB connection (task 007) → begin isolation (rollback default, via task 016) in `beforeEach`/setUp → rollback/truncate (task 016) in `afterEach`/tearDown.
  - **CRITICAL — same connection instance**: the isolation transaction MUST be opened (and rolled back) on the SAME `ConnectionInterface` object that the booted store's container binds as `ConnectionInterface::class` (so container-resolved repositories share the transaction). Obtain that connection from the `BootedStore`/lifecycle — do NOT construct a second connection for `DatabaseTestHelper`. A repository-driven write-then-rollback test must prove isolation works (not just a direct `execute()` on the helper's own connection).
  - Expose `$this->store` (BootedStore) and convenience accessors (`$this->get(id)`, `$this->inScope(...)`).
  - `skipIfUnavailable()` when DB env missing (reuse task 003 guard) so non-DB environments skip cleanly.
  - Honor an isolation-mode switch (rollback default; truncate opt-out) per test/file.
- **Profile dataset**: provide a reusable Pest dataset `storeProfiles` mapping `'simple' => fn() => StoreProfile::simple()`, `'two-locales' => …`, `'two-markets' => …`, so a single test body runs across all three (invariant matrix in task 011). Document how a package registers it (shared dataset file in the testing package, included via Pest).
- Worker token: use the same token source as task 008 so the base case and lifecycle agree on the worker DB.
- Keep the base usable both as a PHPUnit-style base class AND via Pest `uses()` — match how existing package `tests/Pest.php` files wire things.

## Requirements (Test Descriptions)
- [x] `it provisions and boots the chosen profile and exposes the store` (group integration-destructive)
- [x] `it rolls back changes so each test starts clean` (write a row VIA A CONTAINER-RESOLVED REPOSITORY in one test, assert absent in the next — proves the repo connection and the isolation transaction are the same instance; group integration-destructive)
- [x] `it resolves real repositories from the booted store that hit the worker database` (group integration-destructive)
- [x] `it runs the same test body across all profiles via the storeProfiles dataset` (group integration-destructive)
- [x] `it skips when the database is unavailable`
- [x] `it runs a test in truncate isolation mode when selected` (group integration-destructive)

## Acceptance Criteria
- A package can write an integration test by extending/using `IntegrationTestCase`, choosing a profile, with auto schema + isolation + booted store.
- `storeProfiles` dataset runs a body across all three profiles.
- Graceful skip without DB.
- PHPStan level 8 clean (run with `php -d memory_limit=2G`).

## Implementation Notes

### Files Created
- `packages/testing/src/IntegrationTestCase.php` — lifecycle orchestrator: DatabaseProvisioner + StoreProfile::boot + TestIsolation, exposes `$this->store`, `setUpIntegration()`, `tearDownIntegration()`, `tearDownClass()`, `get()`, `inScope()`, `static skipIfUnavailable()`
- `packages/testing/src/storeProfilesDataset.php` — documentation-only file showing the `storeProfiles` dataset pattern for consumer packages
- `packages/testing/tests/Feature/Integration/IntegrationTestCaseTest.php` — all 6 requirement tests (8 actual cases with dataset expansion)

### Design Decisions
- `IntegrationTestCase` is a plain class (NOT extending PHPUnit TestCase) to avoid lifecycle conflicts with Pest's own test case management. It is instantiated manually in each test or can be wrapped via Pest `uses()`.
- The `storeProfiles` dataset is defined directly in the test file (scope = test file) rather than in `tests/Pest.php`. This is because in Pest 4, `tests/Pest.php` is only loaded as bootstrap when Pest is the test runner root — in a monorepo phpunit.xml setup, only one test path's `Pest.php` is bootstrapped. File-scoped datasets are always available to their own file.
- The dataset uses a closure returning arrays of `[StoreProfile]` (not closures-as-values), matching Pest 4's `processDatasets()` contract.
- `storeProfilesDataset.php` in `src/` serves as documentation for consumer packages to copy the dataset definition into their own `tests/Datasets.php`.
