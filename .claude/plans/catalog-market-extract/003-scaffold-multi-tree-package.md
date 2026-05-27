# Task 003: Scaffold `markommerce/catalog-market-category-trees`

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Scaffold the new package that will host the moved entity/repository/exception plus the new resolver, assignment service, and delete plugin. This task only creates the skeleton — file moves and new services land in subsequent tasks (004 / 005 / 006). The skeleton has empty `src/` (gitkeep), a minimal `module.php` returning an empty array, and the standard test harness.

## Context
- Pattern reference: `packages/catalog-scope/` and `packages/catalog-storefront/` for fresh-package scaffolding.
- `composer.json` requires `markommerce/catalog`, `markommerce/market`, `marko/core`, `marko/database`. Autoload `Markommerce\CatalogMarketCategoryTrees\` → `src/`, dev autoload `Markommerce\CatalogMarketCategoryTrees\Tests\` → `tests/`.
- The long namespace (`Markommerce\CatalogMarketCategoryTrees\`) is intentional per FEATURES.md's naming convention.
- Root `composer.json` needs `markommerce/catalog-market-category-trees` added to `require` and `Markommerce\CatalogMarketCategoryTrees\Tests\\` added to `autoload-dev.psr-4`.

## Requirements (Test Descriptions)
- [x] `it requires markommerce/catalog, markommerce/market, marko/core, and marko/database in composer.json`
- [x] `it declares itself as a marko-module via composer extra.marko.module=true`
- [x] `it autoloads the Markommerce\\CatalogMarketCategoryTrees\\ namespace from src/`
- [x] `it autoloads the Markommerce\\CatalogMarketCategoryTrees\\Tests\\ namespace from tests/ in autoload-dev`
- [x] `it ships a module.php returning a valid Marko module manifest array`
- [x] `it registers the package in the root composer.json require block and adds Markommerce\\CatalogMarketCategoryTrees\\Tests\\ to autoload-dev.psr-4`

## Acceptance Criteria
- `packages/catalog-market-category-trees/{composer.json,LICENSE,.gitattributes,module.php,src/.gitkeep,tests/Pest.php,tests/PackageScaffoldingTest.php}` exist.
- README is **not** required in this task (lands in task 010 once the package's behaviour is final).
- `tests/PackageScaffoldingTest.php` MUST NOT include any assertion about README presence — that arrives in task 010 alongside the README. Including a README assertion here will make the package test suite red until task 010 lands.
- Root `composer.json` updated.
- `composer dump-autoload` succeeds; the package test suite is green.
- PHPStan + PHP-CS-Fixer clean.

## Implementation Notes
- Package scaffolded at `packages/catalog-market-category-trees/` following the `catalog-scope` and `catalog-storefront` patterns.
- `module.php` returns an empty array as this is a skeleton-only task.
- Root `composer.json` updated with `markommerce/catalog-market-category-trees` in require and `Markommerce\CatalogMarketCategoryTrees\Tests\` in autoload-dev.psr-4 (both alphabetically ordered within their groups).
- No README created per task requirement (lands in task 010).
- PHPStan excludes tests directories by config so test-file type issues are not a concern.
