# Task 005: Drop the overrides column and GIN index from config-pgsql

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Update `config-pgsql/src/Schema/ConfigValuesTableEmitter.php` to emit a `config_values` table without the `overrides JSONB NOT NULL DEFAULT '{}'::jsonb` column and without the `config_values_overrides_gin` GIN index. Update `config-pgsql/src/PgsqlConfigStorage.php` to drop `overrides` from SELECT/INSERT/UPDATE statements and from the row-hydration path. Update the existing feature tests to drop assertions about the column/index and to drop override read/write cases (relocate those to `config-scope-pgsql`'s integration test in task 011). Update `config-pgsql/README.md` to remove mentions of per-scope overrides.

## Context
- Related files:
  - `packages/config-pgsql/src/Schema/ConfigValuesTableEmitter.php`
  - `packages/config-pgsql/src/PgsqlConfigStorage.php`
  - `packages/config-pgsql/tests/Feature/ConfigValuesTableEmitterTest.php`
  - `packages/config-pgsql/tests/Feature/PgsqlConfigStorageTest.php`
  - `packages/config-pgsql/README.md`
  - `packages/config-pgsql/tests/Feature/Helpers/PostgresTestConnection.php` (verify still works)
- Patterns to follow: keep the existing `IF NOT EXISTS` idempotent emit pattern; the only changes are which columns the CREATE TABLE statement lists and which methods PgsqlConfigStorage uses to read/write rows.
- The "empty row" semantics after this task: `value === null` triggers DELETE. The old composite check `value === null && overrides === '{}'::jsonb` simplifies. PgsqlConfigStorage's `handleEmptyRow`, `handleInsert`, and `handleUpdate` private helpers all have the `overrides` column referenced today — every SELECT, INSERT, UPDATE, and the `hydrateRow` method must drop the column. The `$row->overrides` reads in `handleInsert`/`handleUpdate` will fail to compile after task 001's `ConfigRow` strip, so this task is unblocked only after 001 lands.

## Requirements (Test Descriptions)
- [ ] `it emits a CREATE TABLE config_values statement with config_key, value, version, and updated_at columns and no overrides column`
- [ ] `it emits no CREATE INDEX statement for a GIN index on overrides`
- [ ] `it returns a list with exactly one statement from ConfigValuesTableEmitter createStatements (down from two)`
- [ ] `it persists a global value via PgsqlConfigStorage compareAndSave with no overrides serialization`
- [ ] `it loads a global value via PgsqlConfigStorage load and hydrates ConfigRow with key, value, version, updatedAt only`
- [ ] `it deletes the row from PgsqlConfigStorage compareAndSave when the new row has value=null and the version matches`
- [ ] `it does not reference overrides or _overrides_gin in any SQL statement issued by PgsqlConfigStorage`
- [ ] `it does not import Markommerce\Scope\... namespaces from any file under packages/config-pgsql/src after task completes`

## Acceptance Criteria
- All requirements have passing tests (the Pgsql integration tests run in the `integration-destructive` group).
- `grep -rn "overrides\|_overrides_gin\|Markommerce\\Scope" packages/config-pgsql/src` returns zero matches.
- PHPStan level 8 clean for all touched files.
- README no longer references per-scope override storage.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
