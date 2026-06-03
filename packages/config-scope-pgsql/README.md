# markommerce/config-scope-pgsql

PostgreSQL driver for scope-aware config resolution in Markommerce --- persists scoped configuration value overrides using the `markommerce/config-scope` contracts.

## Installation

```bash
composer require markommerce/config-scope-pgsql
```

Installs `markommerce/config-scope` automatically as a transitive dependency. Run `php marko db:migrate` after installing to create the `config_value_overrides` table.

## Schema

`markommerce/config-scope-pgsql` ships a `ConfigValueOverridesTableEmitter` that generates the DDL for the `config_value_overrides` table. Run Marko's migrate command to apply it:

```bash
php marko db:migrate
```

This creates the following schema (idempotent --- safe to run multiple times):

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

Each row stores one scoped override: a `config_key` (the dot-separated config key string), a `signature` (serialized scope signature such as `locale:de`), and the JSONB `value`.

## Documentation

Full usage, API reference, and schema details: [markommerce/config-scope-pgsql](https://markommerce.dev/docs/packages/config-scope-pgsql/)
