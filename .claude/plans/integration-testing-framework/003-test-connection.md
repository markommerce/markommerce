# Task 003: `TestConnection` + admin/maintenance connection

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create a single reusable `TestConnection` (replacing the 8 copied `PostgresTestConnection` helpers) that connects to a named database from `DB_*` env, plus an admin-level connection capable of `CREATE DATABASE` / `DROP DATABASE` / `CREATE DATABASE … TEMPLATE …` against the maintenance database. This is the connection primitive the schema provisioner and DB lifecycle build on.

## Context
- Model on the existing helper: `packages/catalog/tests/Feature/Helpers/PostgresTestConnection.php` (extends `PgSqlConnection`, reads `DB_HOST/PORT/DATABASE/USERNAME/PASSWORD`, `skipIfUnavailable()` that `markTestSkipped`s when env missing, overrides `createPdo` to point at the env DB).
- Live in `packages/testing/src/` (e.g. `Database/TestConnection.php`, `Database/AdminConnection.php` or a factory).
- `TestConnection` must accept a TARGET database name (not just the env default) so per-worker cloned DBs can be connected to. Keep `skipIfUnavailable()` (a static guard usable from Pest `beforeEach`).
- Admin connection: connect to a maintenance DB (e.g. `postgres`, or env `DB_ADMIN_DATABASE` defaulting to `postgres`) to issue `CREATE/DROP DATABASE`. CONFIRMED: `ConnectionInterface::execute()` runs these in PDO autocommit (no auto-transaction wrapping). `CREATE DATABASE` must NOT run inside a transaction — ensure no surrounding txn.
- Reuse marko's `PgSqlConnection` (`marko/packages/database-pgsql`) rather than reimplementing PDO logic; subclass/compose as the existing helper does.
- Provide a way to build a `ConnectionInterface` for a given DB name (used by provisioner + lifecycle + bootstrapped container binding).

## Requirements (Test Descriptions)
- [x] `it skips when required database env vars are absent`
- [x] `it connects to the database named in DB env and runs a trivial query` (group integration-destructive)
- [x] `it connects to an explicitly provided database name` (group integration-destructive)
- [x] `it creates and drops a database via the admin connection` (group integration-destructive)
- [x] `it creates a database from a template database` (group integration-destructive)
- [x] `it exposes a ConnectionInterface bound to a given database name`
- [x] `it rejects an unsafe database identifier` (validation)

## Acceptance Criteria
- One `TestConnection` usable across all packages; env-driven; graceful skip.
- Admin connection performs CREATE/DROP/TEMPLATE DATABASE outside any transaction.
- DB-touching tests tagged `->group('integration-destructive')`.
- PHPStan level 8 clean (run with `php -d memory_limit=2G`).

## Implementation Notes
- `TestConnection` lives in `packages/testing/src/Database/TestConnection.php` (namespace `Markommerce\Testing\Database`). Extends `PgSqlConnection`, reads `DB_*` env vars, accepts optional `$database` constructor arg (defaults to `DB_DATABASE`), overrides `createPdo()` to use env-var DSN.
- `AdminConnection` lives in `packages/testing/src/Database/AdminConnection.php`. Extends `TestConnection`, connects to `DB_ADMIN_DATABASE` (defaulting to `postgres`). Provides `createDatabase()`, `dropDatabase()` (idempotent via `DROP DATABASE IF EXISTS ... WITH (FORCE)`), `createDatabaseFromTemplate()`, and `connectionFor()`.
- `InvalidIdentifierException` in `packages/testing/src/Database/Exceptions/` validates identifiers against `/^[a-zA-Z_][a-zA-Z0-9_]*$/`.
- `skipIfUnavailable()` uses `test()->markTestSkipped()` (Pest context always available for this testing-only class); PHPStan ignore comment added.
- Tests in `packages/testing/tests/Feature/Database/TestConnectionTest.php`; DB-touching tests tagged `->group('integration-destructive')`.
