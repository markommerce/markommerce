# Task 020: Add curated `position` column to category assignments

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Add a per-category curated ordering column `position` to `ProductCategoryAssignment` (`catalog_product_category`) plus its migration, so the storefront listing can default to merchant-curated category position (the listing's default sort, see Tasks 010/012).

## Context
- Entity file: `packages/catalog/src/Entity/ProductCategoryAssignment.php`. It currently has `id`, `productId` (`product_id`), `categoryId` (`category_id`) and a unique index on `(product_id, category_id)`. Add:
  ```php
  #[Column(name: 'position', type: 'integer', nullable: false)]
  public int $position = 0;
  ```
- Migration: schema is attribute-driven (`Marko\Database\Migration\Migration`); migration files live in the consuming app at `database/migrations/` (project root, e.g. `/home/michal/www/marko/playground/database/migrations/`) named `{YYYYmmddHHmmss}_alter_catalog_product_category.php`. Mirror the existing `create_catalog_product_category` migration. The `up()` runs `ALTER TABLE "catalog_product_category" ADD COLUMN "position" INTEGER NOT NULL DEFAULT 0;` and `down()` drops the column. (A `position` column precedent exists: `…_create_catalog_category_tree_nodes.php` uses `"position" INTEGER NOT NULL DEFAULT 0`.)
- Repository: optionally extend `ProductCategoryAssignmentRepository::findByCategory()` to order by `position` for non-paginated callers, but the paginated path (Task 012) orders explicitly — do not duplicate ordering logic.
- Do NOT add `position` to the unique index; it is an ordering hint, not part of identity. Default `0` keeps existing rows valid.
- Standards: `declare(strict_types=1)`, `#[Column]` attribute typed `integer`, non-null with default `0`.

## Requirements (Test Descriptions)
- [x] `it exposes a position property defaulting to zero on a new assignment`
- [x] `it persists and reads back the assignment position` (DB-backed)
- [x] `it maps the position property to the position column`
- [x] `the migration adds a non-null position column defaulting to zero`

## Acceptance Criteria
- `ProductCategoryAssignment` has a `position` column mapped and defaulting to `0`.
- A reversible migration adds/drops the column following the project's migration pattern.
- Existing assignment tests still pass (default `0` does not break the unique index or current inserts).
- All requirements have passing tests.

## Implementation Notes

- Added `#[Column(name: 'position', type: 'integer', nullable: false)] public int $position = 0;` to `ProductCategoryAssignment` entity.
- Migration file `20260604120000_alter_catalog_product_category.php` created in `/home/michal/www/marko/playground/database/migrations/` with `up()` adding `"position" INTEGER NOT NULL DEFAULT 0` and `down()` dropping it.
- Integration test uses `CREATE TABLE IF NOT EXISTS` with a conditional `ALTER TABLE` to add the column if missing, ensuring compatibility whether or not the migration has been run.
- All 4 requirements pass; `composer test` runs 1831 tests with 0 failures.
