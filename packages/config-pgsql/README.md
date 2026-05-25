# markommerce/config-pgsql

PgSQL (PostgreSQL) storage driver for `markommerce/config` --- persists configuration values in a `config_values` JSONB table with optimistic locking and per-scope overrides.

## Installation

```bash
composer require markommerce/config-pgsql
```

Installs `markommerce/config` automatically as a transitive dependency. Run `php marko db:migrate` after installing to create the `config_values` table.

## Environment Variables

| Variable | Required | Description |
|---|---|---|
| `DB_HOST` | Yes | PostgreSQL host |
| `DB_PORT` | No | PostgreSQL port (default: 5432) |
| `DB_DATABASE` | Yes | Database name |
| `DB_USERNAME` | Yes | Database user |
| `DB_PASSWORD` | Yes | Database password |
| `MARKOMMERCE_CONFIG_SECRET_KEY` | If secrets used | Base64-encoded libsodium key for encrypting secret config values |

## Quick Example

```bash
# After installing and running the migration
php marko config:set shop/display.name "My Store"
php marko config:get shop/display.name
# My Store
```

## Documentation

Full usage, API reference, schema details, and optimistic-locking behavior: [markommerce/config-pgsql](https://markommerce.dev/docs/packages/config-pgsql/)
