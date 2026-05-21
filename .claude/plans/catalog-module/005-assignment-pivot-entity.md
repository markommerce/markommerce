# Task 005: ProductCategoryAssignment Pivot Entity

**Status**: completed
**Depends on**: 003, 004
**Retry count**: 0

## Description
Create the `ProductCategoryAssignment` pivot entity that links products to categories (many-to-many). Each row represents one product assigned to one category.

## Context
- Create at `packages/catalog/src/Entity/ProductCategoryAssignment.php`.
- Extends `Marko\Database\Entity\Entity`. It is NOT scoped — no `HasScopes`.
- Mapped to table `catalog_product_category` via `#[Table('catalog_product_category')]`.
- Properties:
  - `id`: `#[Column(primaryKey: true, autoIncrement: true)] public ?int $id = null;`
  - `productId`: integer column referencing `catalog_products`. Give it an explicit snake_case DB column name (`#[Column(name: 'product_id', references: 'catalog_products', onDelete: 'CASCADE')]`) so the column name is `product_id`, consistent with the snake_case table names. See `vendor/marko/database/src/Attributes/Column.php` for the available parameters (`name`, `references`, `onDelete`, `onUpdate`, `unique`).
  - `categoryId`: `#[Column(name: 'category_id', references: 'catalog_categories', onDelete: 'CASCADE')]`.
- A product must not be assignable to the same category twice. The service layer enforces idempotency (task 009), but — mirroring the SKU "DB index + service check" double-guard decision — add a DB-level composite unique constraint on (`product_id`, `category_id`) via the `#[Marko\Database\Attributes\Index]` class attribute: `#[Index(name: 'uniq_catalog_product_category', columns: ['product_id', 'category_id'], unique: true)]`. The `Index` attribute is `IS_REPEATABLE` and takes `name`, `columns` (a `list<string>` of DB column names), and `unique`.
- Keep it minimal — just the foreign-key columns. No timestamps.
- No `final`. `declare(strict_types=1);`.

## Requirements (Test Descriptions)
- [x] `it maps the assignment entity to the catalog_product_category table`
- [x] `it exposes an auto-increment integer primary key id`
- [x] `it has a product_id column referencing the catalog_products table`
- [x] `it has a category_id column referencing the catalog_categories table`
- [x] `it declares a composite unique index over product_id and category_id`
- [x] `it does not implement HasScopesInterface`

## Acceptance Criteria
- All requirements have passing tests
- Entity attributes are parseable by `EntityMetadataFactory`
- Code follows code standards

## Implementation Notes
- Entity created at `packages/catalog/src/Entity/ProductCategoryAssignment.php`
- Extends `Marko\Database\Entity\Entity` with no `HasScopes` trait (not scoped)
- Uses `#[Table('catalog_product_category')]` class attribute
- Three properties: `id` (PK, auto-increment), `productId` (FK to catalog_products), `categoryId` (FK to catalog_categories)
- Composite unique index declared via `#[Index(name: 'uniq_catalog_product_category', columns: ['product_id', 'category_id'], unique: true)]`
- Test file at `packages/catalog/tests/Unit/Entity/ProductCategoryAssignmentTest.php`
- All 6 tests pass; full catalog suite (32 tests) green
