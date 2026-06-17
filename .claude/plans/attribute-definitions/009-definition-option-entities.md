# Task 009: `AttributeDefinition` + `AttributeOption` entities

**Status**: complete
**Depends on**: 003
**Retry count**: 0

## Description
Create the two persistence entities: `AttributeDefinition` (a merchant-defined attribute) and
`AttributeOption` (a value choice for select/multiselect definitions). Both use Marko
`#[Table]`/`#[Column]` attributes.

## Context
- Pattern: `packages/catalog/src/Entity/Product.php` (extends `Marko\Database\Entity\Entity`,
  `#[Table]`, `#[Column]`).
- Place in `packages/attribute/src/Entity/`.
- `AttributeDefinition` **implements `AttributeDefinitionInterface`** (task 003) so the type
  system can `cast()` against it. Provide the interface getters (`code()`, `entityType()`,
  `type()`, `backing()`, `isRequired()`, `config()`) reading the stored columns/properties.
  `config()` returns the decoded JSONB as `array` (default `[]` when the column is null).
  - `#[Table('attribute_definitions')]`.
  - `id` (PK, autoincrement), `code` (string), `entityType` (`entity_type`, string),
    `type` (string type code), `label` (string), `required` (bool, default false),
    `defaultValue` (`default_value`, nullable text), `backing` (`AttributeBacking`, default
    `Json` — store as its string value), flags `filterable`/`searchable`/`facetable`/`scopable`
    (bool, default false), `config` (JSONB, nullable — type-specific params).
  - Unique constraint on `(entity_type, code)`: Marko's `#[Column]`/`#[Table]` attributes are
    confirmed to support single-column `unique: true` and FK `references:`/`onDelete:`, but no
    entity in the codebase declares a **composite** unique via attributes and `#[Index]` is not
    used anywhere. Treat the composite `UNIQUE (entity_type, code)` as **not guaranteed
    expressible via entity attributes**: enforce uniqueness authoritatively in the service
    (task 012, via `findByCode`), and add the DB-level composite unique in task 014 only if the
    Marko attribute API supports it (otherwise emit it as a raw `ALTER TABLE ... ADD CONSTRAINT`
    in the pgsql package — see task 014). Do NOT block on a DB constraint for the service guard.
- `AttributeOption` (`#[Table('attribute_options')]`):
  - `id` (PK, autoincrement), `attributeId` (`attribute_id`, int FK → definition),
    `value` (string), `label` (string), `position` (int, default 0).
- Entities are data carriers; validation/orchestration lives in the service (task 012).

## Requirements (Test Descriptions)
- [x] `it maps AttributeDefinition to the attribute_definitions table with its columns`
- [x] `it implements AttributeDefinitionInterface and exposes the getters`
- [x] `it returns an empty array from config when the config column is null`
- [x] `it defaults a new AttributeDefinition backing to Json`
- [x] `it defaults the boolean flag columns to false`
- [x] `it maps AttributeOption to the attribute_options table with its columns`
- [x] `it stores type-specific params in the config column`

## Acceptance Criteria
- Entity metadata parses cleanly via the Marko entity tooling.
- Column names follow snake_case; property names camelCase.

## Implementation Notes
- `AttributeBacking` is a pure (unit) enum — not a `BackedEnum` — so Marko's hydrator cannot auto-convert it. Stored as `string` column (default `'Json'`); `backing()` converts via a `match` expression.
- `config` column uses `type: 'json'` with `?array` PHP type (nullable array). `config()` returns `$this->config ?? []` to normalise null → empty array.
- `AttributeOption.attribute_id` FK uses `references: 'attribute_definitions', onDelete: 'CASCADE'`.
- All 7 tests pass (51 total in package, all green).
