# Task 001: Scaffold `markommerce/catalog-attribute` package

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Create the new `markommerce/catalog-attribute` binding package (composer.json, autoload,
`module.php` skeleton, Pest bootstrap, `src/`+`tests/` layout). It binds the entity-agnostic
`markommerce/attribute` kernel to `Product`. No domain logic yet — a loadable, testable shell.

## Context
- Mirror a sibling binding package: STUDY `packages/catalog-scope/composer.json` and
  `packages/catalog-market/composer.json` for structure.
- `composer.json`: `type: marko-module`, `extra.marko.module: true`, PSR-4
  `Markommerce\CatalogAttribute\` → `src/`, tests `Markommerce\CatalogAttribute\Tests\` → `tests/`.
  Requires `php: ^8.5`, `markommerce/catalog: self.version`, `markommerce/attribute: self.version`;
  require-dev `markommerce/testing`, `pestphp/pest: ^4.0`.
- Copy `tests/Pest.php` shape from a sibling package.
- Add `markommerce/catalog-attribute` to the ROOT `composer.json` `require` + the test namespace to
  root `autoload-dev` (mirror how `catalog-scope` is wired in root composer).
- Add a `.gitattributes` + `LICENSE` like sibling packages.

## Requirements (Test Descriptions)
- [x] `it autoloads a class from the Markommerce\CatalogAttribute namespace`
- [x] `it marks the catalog-attribute package as a marko module in composer extra`
- [x] `it declares markommerce/catalog and markommerce/attribute as dependencies`

## Acceptance Criteria
- Package autoloads under its PSR-4 namespace; `composer test` discovers its suite.
- Layout matches the `catalog-scope`/`catalog-market` convention.

## Implementation Notes
- Created `packages/catalog-attribute/` with: `composer.json`, `src/Module.php` (placeholder class), `tests/Pest.php`, `tests/PackageScaffoldingTest.php`, `module.php`, `LICENSE`, `README.md`, `.gitattributes`
- Added `markommerce/catalog-attribute: self.version` to root `composer.json` `require` block
- Added `Markommerce\CatalogAttribute\Tests\` PSR-4 entry to root `composer.json` `autoload-dev`
- The package `require-dev` includes both `marko/testing` (required by monorepo PackageStandard tests) and `markommerce/testing`
- `composer update markommerce/catalog-attribute` was run in Docker to register the package in the lock file
