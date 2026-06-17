# markommerce/attribute-pgsql

PostgreSQL storage driver for `markommerce/attribute` — persists attribute definitions and options in `attribute_definitions` and `attribute_options` tables provisioned from entity metadata.

## Installation

```bash
composer require markommerce/attribute-pgsql
```

Installs `markommerce/attribute` automatically as a transitive dependency.

## What it provides

`PgSqlAttributeDefinitionRepository` implements `AttributeDefinitionRepositoryInterface` and persists to two tables:

| Table | Purpose |
|---|---|
| `attribute_definitions` | One row per attribute definition (`code`, `entity_type`, `type`, `backing`, `required`, `config` JSONB) |
| `attribute_options` | Allowed option values for `select` and `multiselect` attributes (cascade-deleted by the repository when a definition is deleted) |

Tables are provisioned from entity metadata — no hand-written `CREATE TABLE` is needed. The module binding is declared in `module.php`:

```php
AttributeDefinitionRepositoryInterface::class => PgSqlAttributeDefinitionRepository::class,
```

## Environment Variables

| Variable | Required | Description |
|---|---|---|
| `DB_HOST` | Yes | PostgreSQL host |
| `DB_PORT` | No | PostgreSQL port (default: 5432) |
| `DB_DATABASE` | Yes | Database name |
| `DB_USERNAME` | Yes | Database user |
| `DB_PASSWORD` | Yes | Database password |

## Documentation

Full usage, API reference, and schema details: [markommerce/attribute-pgsql](https://markommerce.dev/docs/packages/attribute-pgsql/)
