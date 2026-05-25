# markommerce/config-pgsql

PostgreSQL storage driver for `markommerce/config` --- persists configuration values in a `config_values` JSONB table with optimistic locking and per-scope overrides.

## Installation

```bash
composer require markommerce/config-pgsql
```

Installs `markommerce/config` automatically as a transitive dependency. Run `php marko db:migrate` after installing to create the `config_values` table.

## Quick Example

```bash
# After installing and running the migration
php marko config:set shop/display.name "My Store"
php marko config:get shop/display.name
# My Store
```

## Documentation

Full usage, API reference, schema details, and optimistic-locking behavior: [markommerce/config-pgsql](https://markommerce.dev/docs/packages/config-pgsql/)
