# Task 007: Create the ProductCategory pivot entity

**Status**: completed
**Depends on**: 005, 006
**Retry count**: 0

## Description
Define `Markommerce\Catalog\Entity\ProductCategory` — the pivot entity required by Marko's `#[BelongsToMany]` attribute. It maps the `product_categories` table, exposes the two foreign-key columns, and declares the composite uniqueness index on the entity itself so the generated migration includes it.

## Context
- Related file: `packages/catalog/src/Entity/ProductCategory.php`
- Marko's `BelongsToMany` requires a concrete pivot Entity class (see `marko/database/src/Attributes/BelongsToMany.php`).
- Pattern reference: `marko/admin-auth/src/Entity/RolePermission.php` — surrogate auto-increment `id` PK + two FK columns. Marko ORM only supports single-column primary keys (verified against `Marko\Database\Entity\EntityMetadata::$primaryKey` being a single `string`).
- Extends `Marko\Database\Entity\Entity`, uses `#[Table('product_categories')]`, `#[Column]`, and a class-level `#[Index]` for the composite-uniqueness constraint.
- Columns:
  - `public ?int $id = null` — `#[Column(primaryKey: true, autoIncrement: true)]`. Surrogate PK; no domain meaning.
  - `public int $productId` — `#[Column(references: 'products.id', onDelete: 'CASCADE')]`. The `references` argument triggers FK generation by `SchemaBuilder::buildForeignKeys`.
  - `public int $categoryId` — `#[Column(references: 'categories.id', onDelete: 'CASCADE')]`.
- Class-level attributes:
  - `#[Index(name: 'idx_product_categories_unique', columns: ['product_id', 'category_id'], unique: true)]` — enforces assignment uniqueness at the DB layer. Use the **column** names (`product_id`, `category_id`), not the property names — `#[Index]` operates at the schema level. Verify against `marko/admin-auth/RolePermission` precedent before submission.
- Docblock: explain why this entity exists (pivot requirement of `BelongsToMany`) and that the auto-increment `id` is a Marko ORM constraint, not a domain identifier.
- Schema-shape verification (the unique index, both FKs with cascade) lives in task 011's `SchemaIntegrationTest`.

## Requirements (Test Descriptions)
- [ ] `it maps to the product_categories table via the Table attribute`
- [ ] `it exposes id as a nullable auto-increment primary key`
- [ ] `it exposes productId with a Column reference to products.id and onDelete cascade`
- [ ] `it exposes categoryId with a Column reference to categories.id and onDelete cascade`
- [ ] `it declares a unique class-level Index covering product_id and category_id`
- [ ] `it can be constructed and have its public properties assigned directly`
- [ ] `it satisfies Marko's BelongsToMany pivotClass contract by extending Entity`

## Acceptance Criteria
- File location: `packages/catalog/src/Entity/ProductCategory.php`.
- Tests in `packages/catalog/tests/Unit/Entity/ProductCategoryTest.php` use reflection to assert the FK references, `onDelete` value, and the presence/shape of the class-level `#[Index]`. No database access in this task.
- The class docblock explains why this entity exists (pivot requirement of `BelongsToMany`) so future readers don't try to delete it as redundant.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
