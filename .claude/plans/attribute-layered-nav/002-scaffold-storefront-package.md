# Task 002: Scaffold `markommerce/catalog-attribute-storefront` package

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the new `markommerce/catalog-attribute-storefront` package (the layered-navigation binding:
attribute filter contributor + facet assembler + category-page integration). Composer, `module.php`
skeleton, Pest bootstrap, `src/`+`tests/`, root wiring.

## Context
- Mirror sibling storefront/binding packages: STUDY `packages/catalog-storefront/composer.json` and
  `packages/catalog-price-index/composer.json`.
- `composer.json`: `type: marko-module`, PSR-4 `Markommerce\CatalogAttributeStorefront\` → `src/`,
  tests `Markommerce\CatalogAttributeStorefront\Tests\`. Requires `php: ^8.5`, `marko/core`,
  `marko/database`, `markommerce/catalog`, `markommerce/catalog-attribute`,
  `markommerce/catalog-attribute-index`, `markommerce/catalog-attribute-scope`, `markommerce/attribute`,
  `markommerce/attribute-scope` (for `ScopedOptionLabelResolver` — task 007), `markommerce/scope`,
  `markommerce/criteria`; require-dev `marko/testing`, `markommerce/testing`, `pestphp/pest: ^4.0`.
  (Also requires `markommerce/catalog-storefront` for the category-page integration / `ProductGridData`
  shape used by task 008 — add it.)
- Add to root `composer.json` `require` + test namespace to root `autoload-dev`. Add `.gitattributes`
  + `LICENSE` per siblings.

## Requirements (Test Descriptions)
- [x] `it autoloads a class from the Markommerce\CatalogAttributeStorefront namespace`
- [x] `it marks catalog-attribute-storefront as a marko module in composer extra`
- [x] `it declares markommerce/catalog-attribute-index as a dependency`

## Acceptance Criteria
- Package autoloads; `composer test` discovers its suite. Layout matches sibling conventions.

## Implementation Notes
- Created `packages/catalog-attribute-storefront/` with: `composer.json`, `module.php`, `LICENSE`, `README.md`, `.gitattributes`, `src/Module.php` (minimal placeholder), `tests/Pest.php`, `tests/PackageScaffoldingTest.php`.
- `src/Module.php` is a placeholder class created solely to satisfy the autoload test. A later task should replace it with real domain classes.
- Added `markommerce/catalog-attribute-storefront: self.version` to root `composer.json` `require` and `Markommerce\\CatalogAttributeStorefront\\Tests\\` to root `autoload-dev`.
- Ran `composer update markommerce/catalog-attribute-storefront` inside the Docker container to install the symlinked package and register the autoload mapping.
- A `README.md` was required by the global `FilePresenceTest` standard check.
