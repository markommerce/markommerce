# Task 006: Create the Category entity

**Status**: completed
**Depends on**: 005
**Retry count**: 0

## Description
Define `Markommerce\Catalog\Entity\Category` — the minimum-viable category aggregate. Two columns (`id`, `name`), no parent/tree, no products relationship yet (defined on Product side via `BelongsToMany`). The schema is fully declared on the entity via attributes; no handwritten migration accompanies it.

## Context
- Related file: `packages/catalog/src/Entity/Category.php`
- Pattern reference for entity shape: `marko/admin-auth/src/Entity/Role.php`.
- Schema source: this entity's attributes are the only definition of the `categories` table. Marko's `db:migrate` discovers it and generates the migration. There is no parallel SQL file in this package.
- Extends `Marko\Database\Entity\Entity`, uses `#[Table('categories')]` and `#[Column]` attributes.
- Columns:
  - `public ?int $id = null` — `#[Column(primaryKey: true, autoIncrement: true)]`. Default int type from the attribute parser is sufficient (`INT UNSIGNED` is Marko's default for primary keys).
  - `public string $name` — `#[Column(length: 255)]`. Required, non-nullable. Carry a `@todo multi-store` docblock above it.
- Properties are public per Marko Entity convention (see Role).
- No `created_at` / `updated_at` columns (no Marko abstraction yet; deferred per `_plan.md` Out of Scope).
- Schema-shape verification against a real database is handled by task 011's feature test (`SchemaIntegrationTest`), which materializes the schema via `db:migrate` and asserts `INFORMATION_SCHEMA`. This task's tests are reflection-only.

## Requirements (Test Descriptions)
- [ ] `it maps to the categories table via the Table attribute`
- [ ] `it exposes id as a nullable auto-increment primary key column`
- [ ] `it exposes name as a required string column with length 255`
- [ ] `it carries a multi-store refactor docblock on the name property`
- [ ] `it can be constructed and have its public properties assigned directly`

## Acceptance Criteria
- File location: `packages/catalog/src/Entity/Category.php`.
- Tests in `packages/catalog/tests/Unit/Entity/CategoryTest.php` — assert attribute metadata via reflection (read the `#[Table]` / `#[Column]` arguments off the class/properties) and basic property assignment. No database access.
- `phpstan` clean at level 8.
- Not `final`.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
