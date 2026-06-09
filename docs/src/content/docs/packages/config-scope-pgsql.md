---
title: markommerce/config-scope-pgsql
description: PostgreSQL driver for scope-aware config resolution in Markommerce — persists scoped configuration value overrides in the config_value_overrides table.
---

PostgreSQL driver for scope-aware config resolution in Markommerce. `markommerce/config-scope-pgsql` persists scoped configuration value overrides using the `markommerce/config-scope` contracts. Installing this package provides the persistence layer that `markommerce/config-scope` requires in production.

## Installation

```bash
composer require markommerce/config-scope-pgsql
```

This automatically installs `markommerce/config-scope` as a transitive dependency.

## Configuration

Set the following environment variables so Marko's PostgreSQL driver can connect:

| Variable | Description |
|---|---|
| `DB_HOST` | PostgreSQL host (e.g. `localhost`) |
| `DB_PORT` | PostgreSQL port (default `5432`) |
| `DB_DATABASE` | Database name |
| `DB_USERNAME` | Database user |
| `DB_PASSWORD` | Database password |

## Running the Migration

`markommerce/config-scope-pgsql` ships a `ConfigValueOverridesTableEmitter` that generates the DDL for the `config_value_overrides` table. Run Marko's migrate command to apply it:

```bash
php marko db:migrate
```

## Schema

The migration creates the following schema (idempotent --- safe to run multiple times):

```sql
CREATE TABLE IF NOT EXISTS "config_value_overrides" (
    config_key VARCHAR(255) NOT NULL,
    signature  VARCHAR(255) NOT NULL,
    value      JSONB        NOT NULL,
    version    INTEGER      NOT NULL DEFAULT 0,
    updated_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    PRIMARY KEY (config_key, signature)
);
```

Each row represents one scoped override:

| Column | Type | Description |
|---|---|---|
| `config_key` | `VARCHAR(255)` | The dot-separated config key string (e.g. `shop/display.welcome_message`). |
| `signature` | `VARCHAR(255)` | Serialized scope signature (e.g. `locale:de` or `locale:de\|market:eu`). |
| `value` | `JSONB` | The override value as a JSON-encoded scalar. |
| `version` | `INTEGER` | Row version counter, incremented on every update. |
| `updated_at` | `TIMESTAMPTZ` | Timestamp of the last write. |

The composite primary key `(config_key, signature)` enforces that only one override exists per key+signature pair.

## Storage Behavior

### Upsert semantics

`PgsqlScopedConfigStorage::saveOverride()` uses `INSERT ... ON CONFLICT ... DO UPDATE` to upsert an override row. No optimistic-locking retry is needed for override storage because overrides are independent rows identified by the composite key.

### Delete semantics

`deleteOverride()` runs a single `DELETE WHERE config_key = ? AND signature = ?`. If no row exists, the call is a no-op.

### Batch loading

`loadManyOverrides()` fetches all override rows for a set of keys in a single `IN (...)` query and returns a nested map of `configKey => (signature => value)`.

## Schema Entity

`markommerce/config-scope-pgsql` ships a `ConfigValueOverrideRecord` entity (annotated with `#[Table('config_value_overrides')]`) that declares the `config_value_overrides` table schema. `markommerce/testing`'s `SchemaProvisioner` discovers this entity automatically and creates the table in test databases without requiring migration files.

Because `marko/database` does not support composite primary keys, `ConfigValueOverrideRecord` uses a surrogate autoincrement `id` column as the entity primary key. The business uniqueness constraint on `(config_key, signature)` is expressed as a `#[Index(..., unique: true)]` attribute, which causes `SchemaProvisioner` to emit the corresponding `UNIQUE` index. This unique index is required for `PgsqlScopedConfigStorage`'s `ON CONFLICT (config_key, signature)` upsert to work correctly.

The `updated_at` column has no `DEFAULT` in the entity declaration because Marko cannot emit `DEFAULT NOW()`. `PgsqlScopedConfigStorage` always supplies the current timestamp at write time.

## API Reference

### `PgsqlScopedConfigStorage`

Implements `ScopedConfigStorageInterface`.

| Method | Description |
|---|---|
| `loadOverrides(string $key): array<string, mixed>` | Load all overrides for a config key. Returns a map of serialized signature to raw value. |
| `loadManyOverrides(array $keys): array<string, array<string, mixed>>` | Load overrides for multiple keys in a single query. |
| `saveOverride(string $key, string $signature, mixed $value): void` | Upsert a single override row. |
| `deleteOverride(string $key, string $signature): void` | Remove a single override row. |

### `ConfigValueOverridesTableEmitter`

| Method | Description |
|---|---|
| `createStatements(string $tableName = 'config_value_overrides'): list<string>` | Return the ordered list of SQL statements that create the table. All statements use `IF NOT EXISTS` and are safe to run multiple times. |

## Related Packages

- [markommerce/config-scope](/docs/packages/config-scope/) --- Core scope-aware config package: contracts, resolver, writer, and in-memory fake
- [markommerce/config-pgsql](/docs/packages/config-pgsql/) --- PostgreSQL driver for the base config values table
- [marko/database-pgsql](https://marko.build/docs/packages/database-pgsql/) --- PostgreSQL database driver
