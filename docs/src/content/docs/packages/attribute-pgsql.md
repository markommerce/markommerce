---
title: markommerce/attribute-pgsql
description: PostgreSQL storage driver for markommerce/attribute — persists attribute definitions and options in two auto-provisioned tables.
---

PostgreSQL storage driver for `markommerce/attribute` --- persists attribute definitions and options in `attribute_definitions` and `attribute_options` tables provisioned from entity metadata. Installing this package provides the persistence layer that `markommerce/attribute` requires in production.

## Installation

```bash
composer require markommerce/attribute-pgsql
```

This automatically installs `markommerce/attribute` as a transitive dependency.

## Configuration

Set the following environment variables so Marko's PostgreSQL driver can connect:

| Variable | Required | Description |
|---|---|---|
| `DB_HOST` | Yes | PostgreSQL host (e.g. `localhost`) |
| `DB_PORT` | No | PostgreSQL port (default `5432`) |
| `DB_DATABASE` | Yes | Database name |
| `DB_USERNAME` | Yes | Database user |
| `DB_PASSWORD` | Yes | Database password |

## Schema

`markommerce/attribute-pgsql` ships `AttributeDefinition` and `AttributeOption` as annotated entities. Marko's schema provisioner reads these annotations and generates the DDL automatically --- no hand-written `CREATE TABLE` is needed.

### `attribute_definitions`

One row per attribute definition.

| Column | Type | Description |
|---|---|---|
| `id` | `SERIAL` PK | Auto-increment primary key. |
| `code` | `VARCHAR(64)` | Machine-readable code; unique per entity type. |
| `entity_type` | `VARCHAR(64)` | Entity type identifier (e.g. `'product'`). |
| `type` | `VARCHAR(64)` | Attribute type code (e.g. `'text'`, `'select'`). |
| `label` | `VARCHAR(255)` | Human-readable label. |
| `required` | `BOOLEAN` | Whether the value is mandatory. |
| `default_value` | `TEXT` (nullable) | Default value stored as text. |
| `backing` | `VARCHAR(16)` | `'Json'` or `'Column'`. Default: `'Json'`. |
| `filterable` | `BOOLEAN` | Whether the attribute can be used in filters. |
| `searchable` | `BOOLEAN` | Whether the attribute is included in search. |
| `facetable` | `BOOLEAN` | Whether the attribute generates facets. |
| `scopable` | `BOOLEAN` | Whether the attribute supports scoped values. |
| `config` | `JSONB` (nullable) | Type-specific configuration blob. |

### `attribute_options`

Allowed option values for `select` and `multiselect` attributes.

| Column | Type | Description |
|---|---|---|
| `id` | `SERIAL` PK | Auto-increment primary key. |
| `attribute_id` | `INTEGER` | FK to `attribute_definitions.id`. |
| `value` | `VARCHAR(255)` | Option value used in validation. |
| `label` | `VARCHAR(255)` | Human-readable label. |
| `position` | `INTEGER` | Sort order (default `0`). |

Options are cascade-deleted in application code when a definition is deleted via `AttributeDefinitionService::delete()` or `AttributeDefinitionRepositoryInterface::deleteOptionsFor()`.

## Module Binding

The module registers `PgSqlAttributeDefinitionRepository` as the concrete implementation for `AttributeDefinitionRepositoryInterface`:

```php title="packages/attribute-pgsql/module.php"
<?php

declare(strict_types=1);

use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\PgSql\PgSqlAttributeDefinitionRepository;

return [
    'bindings' => [
        AttributeDefinitionRepositoryInterface::class => PgSqlAttributeDefinitionRepository::class,
    ],
];
```

This binding is applied automatically when the module is loaded. No manual wiring is required.

## API Reference

### `PgSqlAttributeDefinitionRepository`

Extends Marko's base `Repository` class and implements `AttributeDefinitionRepositoryInterface`.

| Method | Description |
|---|---|
| `findByCode(string $entityType, string $code): ?AttributeDefinition` | Look up a definition by entity type and code. Returns `null` if not found. |
| `optionsFor(AttributeDefinition $definition): list<AttributeOption>` | Return all options for a definition, ordered by `attribute_id`. |
| `saveOption(AttributeOption $option): void` | Persist a single option (insert or update). |
| `deleteOptionsFor(AttributeDefinition $definition): void` | Remove all options for a definition. |
| `delete(Entity $entity): void` | Delete a definition, cascade-removing its options first. |

## Related Packages

- [markommerce/attribute](/docs/packages/attribute/) --- Core package: type registry, definition service, and value validation
- [marko/database-pgsql](https://marko.build/docs/packages/database-pgsql/) --- PostgreSQL database driver
