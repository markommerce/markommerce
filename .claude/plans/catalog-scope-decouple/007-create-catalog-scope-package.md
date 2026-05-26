# Task 007: Create `markommerce/catalog-scope` package with companion entities (incl. relocated locale seeder)

**Status**: completed
**Depends on**: 003
**Retry count**: 0

## Description
Create the `markommerce/catalog-scope` package: the machinery bridge that adds scope storage to catalog entities. The package ships two companion entities (`ProductScopedOverrides`, `CategoryScopedOverrides`) that extend the parent tables via `#[Table(extends: ...)]` (single-table inheritance — the companion's `scopes` jsonb column is merged into the parent table by `SchemaRegistry::registerEntities()`; there is no separate companion table). Marko's `EntityMetadataFactory::linkExtendersFrom()` runs automatically at boot inside `marko/database`'s `module.php` (line 28) — `catalog-scope`'s own `module.php` does not need to call it explicitly. Companion attachment happens transparently in `EntityHydrator::hydrate()` provided the SELECT includes the `scopes` column.

This task also relocates the locale-aware seeding logic that was removed from `packages/catalog/Seed/CatalogSeeder.php` in task 003. The new `CatalogLocaleSeeder` lives at `packages/catalog-scope/Seed/CatalogLocaleSeeder.php`, runs after `CatalogSeeder`, fetches the plain catalog rows by SKU/name, attaches `ProductScopedOverrides` / `CategoryScopedOverrides` companions with the German + French overrides, and saves them via the catalog repositories.

This task does NOT include the `ScopedProductGridComponent` preference override — that lands in task 008.

## Context
- Related files (new):
  - `packages/catalog-scope/composer.json` (requires `markommerce/catalog`, `markommerce/scope`)
  - `packages/catalog-scope/module.php` (no entries needed for extender linking — `marko/database`'s boot scans all discovered entities via `EntityMetadataFactory::linkExtendersFrom()`; create only if you need to add bindings/preferences later)
  - `packages/catalog-scope/src/Entity/ProductScopedOverrides.php`
  - `packages/catalog-scope/src/Entity/CategoryScopedOverrides.php`
  - `packages/catalog-scope/Seed/CatalogLocaleSeeder.php` (relocated locale-override seeding from catalog)
  - `packages/catalog-scope/tests/Unit/Entity/ProductScopedOverridesTest.php`
  - `packages/catalog-scope/tests/Unit/Entity/CategoryScopedOverridesTest.php`
  - `packages/catalog-scope/tests/Feature/CompanionPersistenceTest.php`
  - `packages/catalog-scope/tests/Unit/Seed/CatalogLocaleSeederTest.php`
- Reference for shape:
  - `packages/scope/tests/Feature/ScopedOverridesPersistenceTest.php` already proves the companion pattern with `linkExtenders` + `attachCompanion` + dirty tracking via `Repository::update`. Note that the test's SQL log shows `UPDATE products` / `INSERT INTO products` containing the `scopes` column — confirming single-table inheritance.
  - `packages/scope/src/Storage/HasScopes.php` for the trait
  - `marko/packages/database/src/Entity/EntityHydrator.php` lines 70-115 for the companion-attach loop (note the silent skip when companion columns are absent from the row, lines 78-86).
- Companion entity shape:
  ```php
  #[Table(extends: Product::class)]
  class ProductScopedOverrides extends Entity implements HasScopesInterface
  {
      use HasScopes;
  }
  ```
  No additional columns beyond the trait's `scopes` jsonb. The `scopes` column lives on `catalog_products` (parent table), not a separate `product_scoped_overrides` table.

## Requirements (Test Descriptions)
- [x] `it declares ProductScopedOverrides with #[Table(extends: Product::class)] and no additional columns`
- [x] `it has ProductScopedOverrides implement HasScopesInterface via the HasScopes trait`
- [x] `it declares CategoryScopedOverrides with #[Table(extends: Category::class)] and no additional columns`
- [x] `it has CategoryScopedOverrides implement HasScopesInterface via the HasScopes trait`
- [x] `it links ProductScopedOverrides as an extender of Product after EntityMetadataFactory::linkExtendersFrom runs against the discovered entity list`
- [x] `it links CategoryScopedOverrides as an extender of Category after EntityMetadataFactory::linkExtendersFrom runs against the discovered entity list`
- [x] `it merges the scopes column into the catalog_products parent table when SchemaRegistry::registerEntities() runs with both Product and ProductScopedOverrides`
- [x] `it attaches a ProductScopedOverrides companion to a hydrated Product when the SELECT row includes the scopes column`
- [x] `it silently omits the companion when a partial SELECT does not include the scopes column (documented gotcha; ScopeResolver falls back to raw value)`
- [x] `it attaches a ProductScopedOverrides companion with scopes=null when the row's scopes column is NULL (no overrides set)`
- [x] `it round-trips a scoped override on Product through save + re-fetch via the catalog ProductRepository (which selects all columns)`
- [x] `it requires markommerce/catalog and markommerce/scope in composer.json`
- [x] `it declares itself as a marko-module via composer extra.marko.module=true`
- [x] `it uses the Markommerce\\CatalogScope\\ namespace for autoload`
- [x] `CatalogLocaleSeeder runs after CatalogSeeder, fetches all products/categories, attaches scoped-override companions with German and French translations, and saves them through the catalog repositories`
- [x] `it declares the Markommerce\\CatalogScope\\Seed\\ PSR-4 autoload entry for the seeder`

## Acceptance Criteria
- All requirements have passing tests.
- The two companion entities exist with correct table-extends configuration.
- A persistence integration test demonstrates the round-trip flow (set override → save → re-fetch → resolve) using the catalog `ProductRepository`/`CategoryRepository` (not a hand-rolled repository).
- `CatalogLocaleSeeder` exists in `packages/catalog-scope/Seed/` and produces the same locale overrides previously embedded in catalog's seeder.
- Code follows project standards.

## Notes on extender linkage
`linkExtendersFrom()` is owned by `marko/database`'s `module.php` (`packages/database/module.php` line 28). When `catalog-scope` is installed:
1. `EntityDiscovery::discoverInVendor()` (or `discoverInModules()` depending on path layout) globs `*/*/src/Entity/*.php` under the vendor/modules root and finds `ProductScopedOverrides.php` + `CategoryScopedOverrides.php`.
2. `EntityMetadataFactory::linkExtendersFrom()` walks the discovered list, finds the `#[Table(extends: Product::class)]` attribute, and calls `linkExtenders(Product::class, [ProductScopedOverrides::class])`.
3. Subsequent calls to `EntityMetadataFactory::parse(Product::class)` return metadata whose `->extenders` array includes the companion class.

Tests should assert against the resulting metadata state, not against any code path inside catalog-scope's own boot (catalog-scope contributes the entity *class*; the wiring runs in marko/database).

## Implementation Notes
- Created `packages/catalog-scope/` as a new `marko-module` package.
- `ProductScopedOverrides` and `CategoryScopedOverrides` are companion entities using `#[Table(extends: ...)]` with no extra columns (only the `scopes` jsonb from `HasScopes` trait).
- `CatalogLocaleSeeder` uses `#[Seeder(name: 'catalog-locale', order: 10)]` to run after `CatalogSeeder` (order 0). It extracts sequence numbers from "Product N"/"Category N" names and writes German/French overrides via `attachCompanion()`.
- Added `markommerce/catalog-scope` to root `composer.json` require + autoload-dev entries (temporary; will be fully normalized in Task 006).
- SchemaBuilder is in `Marko\Database\Entity\SchemaBuilder` namespace (not `Marko\Database\Schema\SchemaBuilder`).
- Renamed the logging connection helper in CompanionPersistenceTest to `makeScopedLoggingConnection` to avoid conflict with `makeCatalogLoggingConnection` in catalog's RepositoryImplementationsTest.
