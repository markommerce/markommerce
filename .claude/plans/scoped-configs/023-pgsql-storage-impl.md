# Task 023: `PgsqlConfigStorage` implementation + integration tests

**Status**: completed
**Depends on**: 021, 022
**Retry count**: 0

## Description
Implement `ConfigStorageInterface` against PostgreSQL. All three methods (`load`, `loadMany`, `compareAndSave`) use parameterized queries against `config_values`. `compareAndSave` is the critical one: it must atomically check the version, perform the update (or insert), and report whether it succeeded — without read-modify-write races between processes.

## Context
- Use `marko/database-pgsql`'s connection abstraction (constructor-injected) — never instantiate PDO directly
- `load`: `SELECT … FROM config_values WHERE config_key = ?` → hydrate into `ConfigRow` (JSONB decoded, signature-keyed overrides map preserved)
- `loadMany`: `SELECT … FROM config_values WHERE config_key = ANY(?)` → return `array<string, ConfigRow>` keyed by config_key
- `compareAndSave` strategy (must distinguish three cases atomically):
  - **Empty row** (`$row->value === null && $row->overrides === []`):
    - First attempt: `DELETE FROM config_values WHERE config_key = ? AND version = ?` → if affected = 1, return true
    - If affected = 0 AND `$expectedVersion === 0`: check existence via `SELECT 1 FROM config_values WHERE config_key = ?` — if no row, return true (idempotent no-op; matches task 006 contract). If a row exists, return false (stale)
    - If affected = 0 AND `$expectedVersion > 0`: return false (stale)
  - **Non-empty row, `$expectedVersion === 0`**: use `INSERT … ON CONFLICT (config_key) DO UPDATE … WHERE config_values.version = 0 RETURNING version`. If the row already exists with version > 0, the WHERE predicate fails and no row is returned → false. If no row exists, INSERT creates version=1 → true.
  - **Non-empty row, `$expectedVersion > 0`**: use a CTE that updates only if a row exists with matching version, and falls through to INSERT only when no row exists at all:
    ```sql
    WITH updated AS (
        UPDATE config_values SET value=?, overrides=?, version=version+1, updated_at=NOW()
        WHERE config_key = ? AND version = ?
        RETURNING version
    ),
    inserted AS (
        INSERT INTO config_values (config_key, value, overrides, version, updated_at)
        SELECT ?, ?, ?, 1, NOW()
        WHERE NOT EXISTS (SELECT 1 FROM config_values WHERE config_key = ?)
          AND ? = 0  -- only insert when caller expected version 0
        RETURNING version
    )
    SELECT version FROM updated UNION ALL SELECT version FROM inserted;
    ```
    If no rows returned, return false (stale — the row was deleted by another writer between load and save, or version mismatched).
- The expected-version-on-insert case: for `$expectedVersion === 0` against a non-existent row, INSERT creates version=1 → return true. For `$expectedVersion > 0` against a non-existent row, return false (stale — the prior load was based on a row that no longer exists).
- `version` is bumped by 1 on every successful save (the SQL handles this — `version = config_values.version + 1`)
- `updated_at` is set to `NOW()` on every save
- Integration tests use a real PostgreSQL test database. `packages/scope-pgsql/` does not yet have a `tests/Feature/Helpers/` to mirror — build a small connection-helper for this driver modeled on `marko/database`'s integration test bootstrap (`marko/packages/database/tests/`). Reuse the Docker Postgres instance from `~/www/marko/compose.yaml`.
- All integration tests in this task must be tagged `->group('integration-destructive')` per `testing.md`. They run under `composer test:all`, not `composer test`.

## Requirements (Test Descriptions)
- [x] `it returns null from load when the config_key row does not exist`
- [x] `it returns a hydrated ConfigRow with decoded value and overrides from load when the row exists`
- [x] `it returns an empty array from loadMany when no requested keys exist`
- [x] `it returns a map keyed by config_key with one entry per existing row from loadMany`
- [x] `it inserts a new row via compareAndSave when expectedVersion is 0 and no row exists`
- [x] `it updates an existing row via compareAndSave when the stored version matches expectedVersion`
- [x] `it bumps version by exactly 1 on every successful compareAndSave`
- [x] `it returns false from compareAndSave when the stored version does not match expectedVersion`
- [x] `it deletes the row when compareAndSave persists a row with null value and empty overrides`
- [x] `it sets updated_at to NOW() on every successful compareAndSave`
- [x] `it is safe under concurrent compareAndSave calls — only one of two simultaneous writers with the same expectedVersion succeeds`
- [x] `it returns true as a no-op when compareAndSave persists an empty row against an absent key with expectedVersion 0`
- [x] `it returns false from compareAndSave when expectedVersion is greater than 0 but the row no longer exists (deleted by another writer)`
- [x] `it serializes a concurrent empty-mutation (delete) and non-empty mutation (override-add) to the same key without losing the non-empty mutation`
- [x] `it serializes two simultaneous INSERTs to a brand-new key — exactly one succeeds, the other returns false`

## Acceptance Criteria
- All integration tests pass against a real PostgreSQL instance (via the Docker compose dev env)
- No PDO calls leak outside the driver — only `marko/database-pgsql` is used
- PHPStan level 8 clean
- The concurrency test uses two separate connections (not a mock) to validate atomicity
- `@throws` tags on all methods that propagate DB errors

## Implementation Notes
(Left blank — filled in by programmer)
