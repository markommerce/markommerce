# markommerce/config-pgsql

PgSQL storage driver for `markommerce/config` — persists configuration values in a `config_values` JSONB table with optimistic locking and per-scope overrides.

## Installation

```bash
composer require markommerce/config-pgsql
```

Installs `markommerce/config` automatically as a transitive dependency.

## Configuration

Set the following environment variables so Marko's PostgreSQL driver can connect:

| Variable | Description |
|---|---|
| `DB_HOST` | PostgreSQL host (e.g. `localhost`) |
| `DB_PORT` | PostgreSQL port (default `5432`) |
| `DB_DATABASE` | Database name |
| `DB_USERNAME` | Database user |
| `DB_PASSWORD` | Database password |

If you store secrets (encrypted config values), also set:

| Variable | Description |
|---|---|
| `MARKOMMERCE_CONFIG_SECRET_KEY` | 32-byte hex key used to encrypt/decrypt secret config values |

## Running the Migration

`markommerce/config-pgsql` ships a `ConfigValuesTableEmitter` that generates the DDL for the `config_values` table and its GIN index. Run Marko's migrate command to apply it:

```bash
php marko db:migrate
```

This creates the following table (idempotent — safe to run multiple times):

```sql
CREATE TABLE IF NOT EXISTS "config_values" (
    config_key   VARCHAR(255) PRIMARY KEY,
    value        JSONB,
    overrides    JSONB        NOT NULL DEFAULT '{}'::jsonb,
    version      INTEGER      NOT NULL DEFAULT 0,
    updated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS "config_values_overrides_gin"
    ON "config_values" USING GIN (overrides);
```

## Quick Example

After installing and running the migration, verify the round-trip with the built-in CLI commands:

```bash
php marko config:set shop.name "My Store"
php marko config:get shop.name
# My Store
```

## Documentation

For full usage, API reference, and scope-based overrides, see:

- Interface package: [`markommerce/config`](packages/config/README.md)
- Full docs: [markommerce/config-pgsql](https://markommerce.dev/docs/packages/config-pgsql/)
