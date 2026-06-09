# Task 016: Per-test isolation — transaction rollback + truncate opt-out

**Status**: completed
**Depends on**: 008
**Retry count**: 0

## Description
Build the per-test ISOLATION half of the lifecycle on top of the worker-clone connection produced by task 008: wrap each test in a transaction that rolls back (default), with a `truncate` opt-out for code that opens its own transaction (marko has no savepoints). This is what gives fast, pollution-free tests without per-test DDL.

## Context
- Inputs: the single worker-clone `ConnectionInterface` instance + the profile table list, both exposed by task 008.
- Use marko's `Marko\Database\Testing\DatabaseTestHelper` (begin/rollback) — it takes a `ConnectionInterface&TransactionInterface`. beginTransaction in setup, rollback in teardown.
- **CRITICAL — same connection instance**: the transaction MUST be begun/rolled-back on the EXACT SAME `ConnectionInterface` object that the booted store's container binds as `ConnectionInterface::class` (so container-resolved repositories share the transaction). Task 008 exposes that one instance; do NOT construct a second connection for `DatabaseTestHelper`. If the transaction is on a different connection than repositories use, rollback isolates NOTHING — the suite would silently leak state. Add a test that writes a row THROUGH A CONTAINER-RESOLVED REPOSITORY and proves it's gone after rollback.
- **No savepoints** (`TransactionInterface::nestedTransactionNotSupported`) → `truncate` opt-out: instead of a wrapping transaction, reset state between tests by clearing the profile tables (table list from task 008). NOTE: marko's `DatabaseTestHelper::truncateTable()` actually issues `DELETE FROM` (not `TRUNCATE`); with the catalog schema having no FK constraints this is fine, but if true `TRUNCATE … CASCADE` (sequence reset) is wanted, issue it directly. Pick and document the behavior.
- Expose an `IsolationMode` enum (`Rollback` default | `Truncate`) and a per-test/per-file switch. Used by code that itself opens a transaction (e.g. batch indexer paths) which would otherwise conflict with a wrapping transaction.
- Live in `packages/testing/src/Database/` (e.g. `IsolationMode.php`, isolation logic in a `TestIsolation`/lifecycle class). Task 009 (`IntegrationTestCase`) orchestrates begin/rollback around each test using this.

## Requirements (Test Descriptions)
- [x] `it defaults to rollback isolation mode`
- [x] `it rolls back row changes between tests in rollback mode` (write via a container-resolved repository, assert absent after rollback; group integration-destructive)
- [x] `it begins and rolls back on the same connection instance the container resolves` (group integration-destructive)
- [x] `it resets profile tables between tests in truncate mode` (group integration-destructive)
- [x] `it selects truncate mode for code that opens its own transaction` (group integration-destructive)

## Acceptance Criteria
- Rollback isolation by default on the shared connection instance; proven via a repository-driven write.
- Truncate opt-out available and documented (DELETE-vs-TRUNCATE behavior stated).
- PHPStan level 8 clean (run with `php -d memory_limit=2G`).

## Implementation Notes

- `IsolationMode` enum lives at `packages/testing/src/Database/IsolationMode.php` with cases `Rollback` and `Truncate`.
- `TestIsolation` class lives at `packages/testing/src/Database/TestIsolation.php`. Constructor takes an optional `IsolationMode` (defaults to `Rollback`). Methods: `begin(ConnectionInterface&TransactionInterface, array<string> $tableNames)` and `finish()`.
- Rollback mode: uses `DatabaseTestHelper::beginTransaction()` / `rollback()` around each test.
- Truncate mode: no wrapping transaction; `finish()` calls `DatabaseTestHelper::truncateTable()` (which issues `DELETE FROM`, not `TRUNCATE`) on each profile table. Sequences are NOT reset between tests.
- The no-op `EventDispatcherInterface` binding is required in test context (the full Application binds it; test containers do not). Tests inject an anonymous no-op implementation before resolving repositories.
- PHPStan level 8 clean; PHP-CS-Fixer and PHPCS pass on both new source files.
