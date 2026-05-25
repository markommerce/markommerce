# Task 022: `config_values` table migration + schema emitter

**Status**: completed
**Depends on**: 021
**Retry count**: 0

## Description
Define the PostgreSQL schema for `config_values` and provide a Marko-compatible migration / schema emitter that creates it. Schema mirrors the storage shape from the plan's architecture notes: single row per config key with JSONB for both the global value and the overrides map, plus an integer version for optimistic locking.

## Context
- Reference: `packages/scope-pgsql/src/Schema/ScopesGinIndexEmitter.php` for Marko's schema-emission pattern
- Table shape:
  ```sql
  CREATE TABLE config_values (
      config_key   VARCHAR(255) PRIMARY KEY,
      value        JSONB,                                  -- nullable: row may exist with only overrides
      overrides    JSONB        NOT NULL DEFAULT '{}'::jsonb,
      version      INTEGER      NOT NULL DEFAULT 0,
      updated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
  );

  CREATE INDEX config_values_overrides_gin ON config_values USING GIN (overrides);
  ```
- The GIN index is for admin-side queries ("find configs with override on `market:EU`") — the resolver always reads by primary key
- `updated_at` is maintained by the driver on every write (not a trigger — explicit)
- Migration must be idempotent (`IF NOT EXISTS`) so re-running on a partial state is safe
- Whether the emitter is a Marko migration file or a runtime DDL emitter depends on what scope-pgsql does — match its pattern exactly

## Requirements (Test Descriptions)
- [x] `it creates the config_values table with config_key as primary key`
- [x] `it creates the value column as JSONB and nullable`
- [x] `it creates the overrides column as JSONB defaulting to empty object`
- [x] `it creates the version column as integer defaulting to 0`
- [x] `it creates the updated_at column as timestamptz`
- [x] `it creates a GIN index on the overrides column`
- [x] `it is idempotent across multiple runs (no duplicate index / table errors)`

## Acceptance Criteria
- Integration test runs migration twice in a row against a real test database with no errors
- PHPStan level 8 clean
- DDL uses `IF NOT EXISTS` consistently

## Implementation Notes
(Left blank — filled in by programmer)
