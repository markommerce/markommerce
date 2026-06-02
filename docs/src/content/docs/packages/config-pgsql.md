---
title: markommerce/config-pgsql
description: PostgreSQL storage driver for markommerce/config — JSONB persistence with optimistic locking.
---

PostgreSQL storage driver for `markommerce/config` --- persists configuration values in a `config_values` JSONB table with optimistic locking. Implements `ConfigStorageInterface` using a `config_key`, `value` (JSONB), `version`, and `updated_at` row shape. Installing this package provides the persistence layer that `markommerce/config` requires in production.

## Installation

```bash
composer require markommerce/config-pgsql
```

This automatically installs `markommerce/config` as a transitive dependency.

## Configuration

Set the following environment variables so Marko's PostgreSQL driver can connect:

| Variable | Description |
|---|---|
| `DB_HOST` | PostgreSQL host (e.g. `localhost`) |
| `DB_PORT` | PostgreSQL port (default `5432`) |
| `DB_DATABASE` | Database name |
| `DB_USERNAME` | Database user |
| `DB_PASSWORD` | Database password |

If you store secret config values (properties with `secret: true`), also set:

| Variable | Description |
|---|---|
| `MARKOMMERCE_CONFIG_SECRET_KEY` | 32-byte raw binary key used to encrypt and decrypt secret config values |

## Running the Migration

`markommerce/config-pgsql` ships a `ConfigValuesTableEmitter` that generates the DDL for the `config_values` table. Run Marko's migrate command to apply it:

```bash
php marko db:migrate
```

This creates the following schema (idempotent --- safe to run multiple times):

```sql
CREATE TABLE IF NOT EXISTS "config_values" (
    config_key   VARCHAR(255) PRIMARY KEY,
    value        JSONB,
    version      INTEGER      NOT NULL DEFAULT 0,
    updated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
```

## Storage Behavior

### Row shape

One row exists per config key. The columns map to the `ConfigRow` value object:

| Column | Type | Description |
|---|---|---|
| `config_key` | `VARCHAR(255)` | Primary key --- the dot-separated config key string. |
| `value` | `JSONB` | Global value. `NULL` when no global value has been written. |
| `version` | `INTEGER` | Optimistic-lock counter. Starts at 0, incremented on every successful write. |
| `updated_at` | `TIMESTAMPTZ` | Timestamp of the last write. |

### Optimistic locking

`PgsqlConfigStorage::compareAndSave()` implements compare-and-swap:

- **Insert** (new row): uses `INSERT ... ON CONFLICT ... DO UPDATE WHERE version = 0` to guarantee exactly one writer wins when a key is written for the first time.
- **Update** (existing row): uses `UPDATE ... WHERE config_key = ? AND version = ?` and checks that one row was affected.
- **Delete** (all values cleared): uses `DELETE ... WHERE config_key = ? AND version = ?`.

A `false` return value signals a version mismatch. `ConfigWriter` retries up to 3 times and throws `StaleConfigWriteException` if all attempts fail.

## API Reference

### `PgsqlConfigStorage`

Implements `ConfigStorageInterface`.

| Method | Description |
|---|---|
| `load(string $key): ?ConfigRow` | Load a single row by key. Returns `null` if the key has never been written. |
| `loadMany(array $keys): array<string, ConfigRow>` | Load multiple rows in a single `IN (...)` query. Returns only keys that exist. |
| `compareAndSave(string $key, ConfigRow $row, int $expectedVersion): bool` | Atomic compare-and-swap. Returns `true` on success, `false` on version mismatch. |

### `ConfigValuesTableEmitter`

| Method | Description |
|---|---|
| `createStatements(string $tableName = 'config_values'): list<string>` | Return the list of SQL statements that create the table. All statements use `IF NOT EXISTS` and are safe to run multiple times. |

## Related Packages

- [markommerce/config](/docs/packages/config/) --- Core package: attributes, resolver, writer, CLI, and in-memory fake
- [marko/database-pgsql](https://marko.build/docs/packages/database-pgsql/) --- PostgreSQL database driver
