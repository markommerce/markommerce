# Task 014: Real-Postgres Integration Tests

**Status**: pending
**Depends on**: 008, 012, 013
**Retry count**: 0

## Description
Add real-Postgres integration tests that exercise the full write/read round-trip, the COALESCE chain in `ORDER BY` (the only position currently supported — see task 013), the GIN index presence, and transactional rollback of set + remove operations. Tagged `->group('integration-destructive')` per project convention; excluded from `composer test`, included in `composer test:all`.

Tests skip with a clear message if the compose Postgres is not reachable — running the destructive suite without Postgres is a known limitation, not a bug.

## Context
- New file: `packages/scope-pgsql/tests/Feature/PostgresIntegrationTest.php`
- New helper file: `packages/scope-pgsql/tests/Feature/Helpers/PostgresTestConnection.php` — bypasses `Marko\Database\Config\DatabaseConfig` (which requires a `config/database.php` file and `ProjectPaths` — incompatible with env-driven test setup). The helper:
  - Reads `getenv('DB_HOST')`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
  - Returns a test-only subclass of `Marko\Database\PgSql\Connection\PgSqlConnection` that overrides the protected `createPdo(string $dsn, string $username, string $password, array $options): PDO` method to build the PDO directly from env vars, bypassing the `DatabaseConfig` constructor.
  - Returns `null` (caller skips) when any required env var is missing. Helper provides a `static skipIfUnavailable(): void` method that calls `test()->markTestSkipped(...)` with the missing-var names.
  - Helper alternative: write a tmp `config/database.php` file pointing at the env-supplied DB and use `ProjectPaths` for it. Less clean — prefer the PDO override.
- The helper does NOT make the `DatabaseConfig` available to user code. It is test-scoped only.
- Pest skip pattern: at the top of the `describe` block (or top of the file), call `PostgresTestConnection::skipIfUnavailable()` so all tests in the file skip together when env is unset.
- Test fixture entity (in the test file): a `Product` with `Scoped(axes: ['channel', 'locale'])` on `$name` and `$price`. Use a unique table name per test run: `'scope_int_' . bin2hex(random_bytes(8))` to avoid collisions across parallel test runs and crashes that leak tables.
- Cleanup pattern:
  - Schema CREATE and DROP statements run OUTSIDE transactions in `beforeEach` / `afterEach` (Postgres permits DDL in transactions, but mixing fixture DDL with assertion-scoped transactions is fragile).
  - Per-test data writes run INSIDE a transaction that the test ROLLBACKs at the end (or commits — `DROP TABLE` in afterEach cleans up either way).
  - `afterEach`: `DROP TABLE IF EXISTS "<table>"` for crash resilience.
- Migration in `beforeEach`: run the schema diff against the empty DB state (creates the table + scopes column via `PgSqlGenerator::generateUp(...)`) AND append the task-012 emitter's `CREATE INDEX IF NOT EXISTS` statements. Execute all statements against the real connection.
- Performance assertion: NOT REQUIRED. Correctness only — performance is verified by code review of the algorithm, not benchmarks (out of scope per the brief).
- `ScopedSelect` / `ScopedWhere` integration tests are dropped (the specs do not exist — see task 013).

## Requirements (Test Descriptions)
- [ ] `it round-trips a composite override write through the walker and reads back the expected value` — uses `ScopeResolver::setOverride` with a two-axis signature, then `resolved()` returns it
- [ ] `it orders rows by the resolved property using the COALESCE chain` — uses `ScopedOrderBy`
- [ ] `the generated candidate signature list for a context + axes matches the expected ordered list when fetched as raw SQL` — assert against the actual emitted query text
- [ ] `exceeding the candidate cap triggers a warning and limits the chain length` — set cap to a small value (e.g. 3) via a test-only enumerator instance; assert warning + truncation
- [ ] `the schema apply creates the scopes JSONB column AND the jsonb_path_ops GIN index in a single sequence (column from PgSqlGenerator, index from the task-012 emitter)`
- [ ] `the GIN index is introspectable via pg_indexes after migration (the indexdef contains "USING gin" and "jsonb_path_ops")`
- [ ] `re-running the schema apply twice is a no-op for the GIN index (the second run does not error and does not create a duplicate index)` — proves IF NOT EXISTS idempotency
- [ ] `setOverride followed by clearOverride inside a transaction that rolls back leaves the database unchanged`
- [ ] `tests skip with a clear message when DB_HOST is unset` (verified via mocked env — set DB_HOST to empty and invoke skipIfUnavailable)

## Acceptance Criteria
- All tests tagged `->group('integration-destructive')`.
- `composer test` (default, parallel) passes WITHOUT this file's tests running.
- `composer test:all` (parallel) passes WITH this file's tests running against the compose Postgres.
- Each test cleans up its table in an `afterEach` hook via `DROP TABLE IF EXISTS`.
- Skip message names the missing env var(s) so users know what to set.
- `phpcs`, `php-cs-fixer --dry-run`, `phpstan` clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
