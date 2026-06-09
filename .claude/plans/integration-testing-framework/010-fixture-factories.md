# Task 010: Fixture factory base + catalog factories

**Status**: completed
**Depends on**: 009
**Retry count**: 0

## Description
Provide a fluent fixture-factory base (object-mother/builder) and concrete catalog factories (`ProductFactory`, `CategoryFactory`, and a price-index helper), backed by REAL repositories resolved from the booted store — replacing the duplicated raw-SQL `priceIndexInsertProduct()`-style helpers with a readable, reusable API.

## Context
- Base in `packages/testing/src/Fixtures/` (e.g. `FixtureFactory` abstract): holds a `BootedStore`/container ref, sensible defaults, `->create()` persisting via the real repository, fluent `with*()` overrides, and batch creation. Mirror the ergonomics of the existing `CatalogSeeder` (`packages/catalog/Seed/CatalogSeeder.php`) which already creates products/categories/assignments via repositories.
- Concrete catalog factories: where they live — the catalog package's own test-support dir (`packages/catalog/tests/Support/`) is the natural home (catalog already has `Fake*Repository` support classes there), consuming the base from `markommerce/testing`. **This task MUST add `markommerce/testing` to `packages/catalog/composer.json` `require-dev` and dump autoload** (it is the FIRST task that places code in catalog depending on the testing package; task 013 later adds the same require-dev to the OTHER packages). Confirm catalog can `require-dev` the testing package without a dependency cycle: `markommerce/testing` resolves modules dynamically from `installed.json` and must NOT declare a runtime `require` on `markommerce/catalog` (only catalog → testing as require-dev). Verify no composer cycle after dump.
  - `ProductFactory::new(BootedStore $store)->withSku()->withName()->withPrice('9.99')->inCategory($category)->create(): Product`
  - `withIndexedPrice('9.99')` — writes a `catalog_product_price_index` row (only valid when the profile includes the price-index module; otherwise throw a clear error).
  - `CategoryFactory::new($store)->withName()->create(): Category`
- Factories resolve repositories from the store's container (e.g. `ProductRepositoryInterface`, `CategoryRepositoryInterface`, `ProductCategoryAssignmentRepositoryInterface`, `ProductPriceIndexRepositoryInterface`) — never hand-rolled SQL.
- Defaults must be unique-per-call (sku, name) to avoid unique-constraint clashes within a test.

## Requirements (Test Descriptions)
- [x] `it creates a product with default attributes via the real repository` (group integration-destructive)
- [x] `it overrides product sku name and price fluently` (group integration-destructive)
- [x] `it assigns a created product to a category` (group integration-destructive)
- [x] `it creates a category via the real repository` (group integration-destructive)
- [x] `it writes an indexed price row when the profile supports it` (group integration-destructive)
- [x] `it fails clearly when indexed price is requested without the price-index module`
- [x] `it generates unique default skus across multiple products` (group integration-destructive)
- [x] `it adds markommerce/testing as a catalog require-dev without a composer dependency cycle`

## Acceptance Criteria
- Fluent factories create persisted data via real repositories from the booted store.
- Catalog factories cover product, category, assignment, indexed price.
- Clear error when a factory feature needs a module the profile lacks.
- PHPStan level 8 clean (run with `php -d memory_limit=2G`).

## Implementation Notes

- `FixtureFactory` base in `packages/testing/src/Fixtures/FixtureFactory.php`: holds `BootedStore`, static counter for unique defaults, auto-registers a no-op `EventDispatcherInterface` in the container (required because the container doesn't handle nullable constructor params).
- `MissingModuleException` in `packages/testing/src/Fixtures/Exceptions/MissingModuleException.php`: thrown when `withIndexedPrice()` is used but the price-index module isn't loaded.
- `ProductFactory` and `CategoryFactory` in `packages/catalog/tests/Support/`: fluent builders persisting via real repositories; `ProductFactory::writeIndexedPrice()` checks `container()->has()` before resolving the price-index repo.
- `packages/catalog/composer.json` `require-dev` updated to add `markommerce/testing: self.version`.
- Test file at `packages/catalog/tests/Feature/Factories/FixtureFactoriesTest.php`.
- PHPStan level 8 clean on all new files; pre-existing cache write errors in scope package tests are unrelated.
