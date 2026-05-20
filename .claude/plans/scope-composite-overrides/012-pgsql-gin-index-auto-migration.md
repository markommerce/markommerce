# Task 012: Auto-Migration — `jsonb_path_ops` GIN Index on `scopes` Column

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Emit a `jsonb_path_ops` GIN index on the `scopes` JSONB column for every entity (or extender) that uses the `HasScopes` trait. The index is created via a raw `CREATE INDEX IF NOT EXISTS` statement executed alongside the normal schema apply. Zero-config for users.

**Why a side-channel and not the schema-diff pipeline**: Marko's schema layer (`IndexType` enum, `Schema\Index`, `Attributes\Index`, `PgSqlGenerator`, `PgSqlIntrospector`) has no GIN or operator-class support today (verified — only `Btree`, `Unique`, `Fulltext` exist). Extending the schema layer is out of scope for this plan. Instead, `markommerce/scope-pgsql` emits the index via a separate raw statement; idempotency is provided by `IF NOT EXISTS` at the SQL level.

## Context
- Independent of the algorithmic refactor — can run in batch 1.
- New file: `packages/scope-pgsql/src/Schema/ScopesGinIndexEmitter.php` (or similar name — programmer's call). Responsibility:
  1. Given a `SchemaRegistry` (or the list of registered entity classes / extender classes), identify every table whose entity (parent or any extender) uses the `HasScopes` trait. Detection: reflection on the class for `uses HasScopes` (PHP `class_uses_recursive` or equivalent) AND existence of a `scopes` column on the resolved table.
  2. For each matching table, return the raw SQL: `CREATE INDEX IF NOT EXISTS "<table>_scopes_gin" ON "<table>" USING GIN ("scopes" jsonb_path_ops)`.
- Integration: the emitter exposes a `additionalSqlForTables(SchemaRegistry $registry): list<string>` method (pure function — no IO). Consumers (the schema-apply CLI command, the integration tests) call this after `PgSqlGenerator::generateUp(...)` and append the returned statements to the apply queue.
- The emitter does NOT modify the `Schema\Table` objects in the registry (Marko's schema model can't represent GIN). It only produces additional raw SQL.
- Idempotency: `IF NOT EXISTS` is Postgres-native. Re-running the apply on a database that already has the index is a Postgres no-op (it parses but does nothing). No `DiffCalculator` participation needed.
- Identifier validation: validate `$table` against `IdentifierValidator::isValidIdentifier()` before interpolating into the SQL string. Throw `InvalidColumnException` otherwise.
- Tests live in `packages/scope-pgsql/tests/Feature/AutoMigrationTest.php` (the existing in-memory schema-diffing file — extend, don't replace). Two layers of test:
  1. Unit-level: given a SchemaRegistry with an entity using HasScopes, the emitter returns the expected `CREATE INDEX IF NOT EXISTS ...` statement.
  2. Integration-level (in task 014, not here): run the emitter's output against a real Postgres; assert the index exists; run twice; assert no error.

## Requirements (Test Descriptions)
- [ ] `the emitter returns a CREATE INDEX IF NOT EXISTS statement for a table whose entity uses HasScopes`
- [ ] `the emitter returns a CREATE INDEX IF NOT EXISTS statement for a table whose extender uses HasScopes (parent does not)`
- [ ] `the emitter returns zero statements for tables whose entity (and extenders) do not use HasScopes`
- [ ] `the emitted statement uses the jsonb_path_ops operator class explicitly`
- [ ] `the emitted index name is "<table>_scopes_gin"`
- [ ] `the emitted statement uses CREATE INDEX IF NOT EXISTS (no separate idempotency machinery)`
- [ ] `the emitted statement quotes the table name and column name with double quotes`
- [ ] `the emitter throws InvalidColumnException when the table name fails IdentifierValidator`
- [ ] `the emitter returns one statement per HasScopes-bearing table when multiple are registered`
- [ ] `the emitter does NOT add a Schema\Index object to the registry (it only emits raw SQL)`

## Acceptance Criteria
- All requirements have passing tests (in `packages/scope-pgsql/tests/Feature/AutoMigrationTest.php` or a new `tests/Unit/Schema/ScopesGinIndexEmitterTest.php`).
- The emitter is exposed as a public class that the schema-apply integration (task 014) can call.
- No changes to Marko's `IndexType`, `Schema\Index`, `Attributes\Index`, `PgSqlGenerator`, or `PgSqlIntrospector`.
- `composer test` is green.
- `phpcs`, `php-cs-fixer --dry-run`, `phpstan` clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
