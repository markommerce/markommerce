# Task 003: Strip scope coupling from Product, Category, and the catalog seeder

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Remove all `Markommerce\Scope\...` imports, `#[Scoped]` attributes, the `HasScopes` trait, and the `HasScopesInterface` implementation from catalog's `Product` and `Category` entities. After this task, both entities are plain `Marko\Database\Entity\Entity` subclasses with only `#[Table]` and `#[Column]` attributes. The `scopes` JSON column ceases to exist on the catalog-owned schema for `catalog_products` and `catalog_categories` — it is contributed back to the *same* tables by `catalog-scope`'s companion entities in task 007 via `#[Table(extends:)]` (single-table inheritance; columns merge into the parent table at schema-registration time).

The entity unit tests (`ProductTest`, `CategoryTest`, `ProductCategoryAssignmentTest`) are updated in this same task: assertions on `#[Scoped]` attributes, `HasScopesInterface` implementation, and the `scopes` column are removed.

This task also strips scope coupling from `packages/catalog/Seed/CatalogSeeder.php` (catalog production code, autoloaded under `Markommerce\Catalog\Seed\`). Specifically:
- Remove the `use Markommerce\Scope\Exceptions\ScopeStorageException;` import.
- Remove the `@throws ScopeStorageException` PHPDoc tags.
- Remove the `$category->setOverride(...)` calls in `seedCategories()` (lines 60-63 of the current file).
- Remove the `$product->setOverride(...)` calls and the `shouldAddOverride()` probability guard in `seedProductsAndAssignments()` (lines 89-97 and the helper method).
- Remove the `LOCALE_OVERRIDE_PROBABILITY_PERCENT` constant.
- The locale-aware variant of this seeder is recreated as `CatalogLocaleSeeder` (or equivalent) inside `markommerce/catalog-scope` under task 007.

## Context
- Related files:
  - `packages/catalog/src/Entity/Product.php`
  - `packages/catalog/src/Entity/Category.php`
  - `packages/catalog/Seed/CatalogSeeder.php`
  - `packages/catalog/tests/Unit/Entity/ProductTest.php`
  - `packages/catalog/tests/Unit/Entity/CategoryTest.php`
  - `packages/catalog/tests/Unit/Entity/ProductCategoryAssignmentTest.php`
  - `packages/catalog/tests/Unit/Seed/CatalogSeederTest.php` (drop expectations that overrides are set on products/categories; assert only the plain `name`/`description`/`sku` columns)
- Other catalog entities (`CategoryTree`, `CategoryTreeNode`, `CategoryTreeMarketAssignment`, `ProductCategoryAssignment`) already have no scope coupling — leave them alone.
- Test assertions to remove:
  - `ProductTest` cases: `does not mark the sku property as scoped`, `marks the name property as scoped on the locale axis`, `marks the description property as scoped on the locale axis`, `implements HasScopesInterface and exposes a scopes storage column` — delete all four.
  - `CategoryTest` parallel cases — delete.
  - `ProductCategoryAssignmentTest` `Markommerce\Scope\Storage\HasScopesInterface` import — remove.
  - `CatalogSeederTest` `DefaultScopeGuard::configure(['locale' => 'default'])` setUp + any assertion that overrides were written — remove (these assertions were stripped along with the seeder logic).
- Assertion style: prefer attribute-reflection (`$reflection->getProperty('name')->getAttributes(Scoped::class)->toHaveCount(0)`) over file-content greps (`file_get_contents()`). Reflection is robust against future file moves.

## Requirements (Test Descriptions)
- [ ] `it maps the Product entity to the catalog_products table` (kept, unchanged)
- [ ] `it exposes an auto-increment integer primary key id on Product` (kept, unchanged)
- [ ] `it declares the sku column as unique on Product` (kept, unchanged)
- [ ] `it does not implement HasScopesInterface on Product`
- [ ] `it does not declare a scopes column on the Product entity reflection`
- [ ] `it has no #[Scoped] attributes on any Product property`
- [ ] `it has no Markommerce\\Scope namespace imports in the Product class file`
- [ ] `it maps the Category entity to the catalog_categories table` (kept, unchanged)
- [ ] `it does not implement HasScopesInterface on Category`
- [ ] `it does not declare a scopes column on the Category entity reflection`
- [ ] `it has no #[Scoped] attributes on any Category property`
- [ ] `it has no Markommerce\\Scope namespace imports in the Category class file`
- [ ] `it seeds plain Product rows with no setOverride() calls from CatalogSeeder`
- [ ] `it seeds plain Category rows with no setOverride() calls from CatalogSeeder`
- [ ] `it has no Markommerce\\Scope namespace imports in CatalogSeeder.php`

## Acceptance Criteria
- All requirements have passing tests.
- `grep -n "Markommerce.Scope" packages/catalog/src/Entity/Product.php packages/catalog/src/Entity/Category.php` returns zero matches.
- `grep -n "Markommerce.Scope\|setOverride\|LOCALE_OVERRIDE" packages/catalog/Seed/CatalogSeeder.php` returns zero matches.
- Existing non-scope assertions on Product/Category (table name, columns, primary key, sku uniqueness) still pass.
- No regression in catalog unit tests other than the deleted scope-specific cases.
- Code follows project standards.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
