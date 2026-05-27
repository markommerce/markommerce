# Task 002: Scaffold `markommerce/catalog-market` no-op bridge

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create `markommerce/catalog-market` as a placeholder field bridge — same shape as `markommerce/catalog-locale` but with an **empty** `boot` closure. `Product` does not yet have `price` or `visibility` columns, so the bridge registers no fields today. A leading docblock in `module.php` documents the placeholder intent and references FEATURES.md's tier 3 row.

## Context
- Mirror package: `packages/catalog-locale/` (`composer.json`, `module.php`, `src/.gitkeep`, `tests/`, `README.md`).
- catalog-locale's `module.php` shape:
  ```php
  return [
      'require' => ['markommerce/catalog-scope' => '*', 'markommerce/locale' => '*'],
      'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
          // catalog-locale registers Product/Category name+description here
      },
  ];
  ```
- catalog-market's `module.php` keeps the same `require` shape (`catalog-scope` + `market`) and an empty boot closure (still typed-hinted on `ScopedFieldRegistry` for future expansion).
- Root `composer.json` needs `markommerce/catalog-market` added to `require` and `Markommerce\CatalogMarket\Tests\\` added to `autoload-dev.psr-4`.

## Requirements (Test Descriptions)
- [x] `it requires markommerce/catalog-scope and markommerce/market in composer.json`
- [x] `it declares itself as a marko-module via composer extra.marko.module=true`
- [x] `it uses the Markommerce\\CatalogMarket\\ namespace for any future autoload`
- [x] `it declares require entries for markommerce/catalog-scope and markommerce/market in module.php`
- [x] `it ships an empty-but-callable boot closure typed on ScopedFieldRegistry`
- [x] `it registers no scoped fields when the boot closure runs against a fresh ScopedFieldRegistry`
- [x] `it registers the package in the root composer.json require block and adds Markommerce\\CatalogMarket\\Tests\\ to autoload-dev.psr-4`
- [x] `it ships a README that follows the project package README standards`

## Acceptance Criteria
- `packages/catalog-market/{composer.json,LICENSE,.gitattributes,README.md,module.php,src/.gitkeep,tests/Pest.php,tests/PackageScaffoldingTest.php,tests/ReadmeTest.php}` exist.
- `module.php` carries a docblock documenting the placeholder status and pointing to FEATURES.md's tier 3 line.
- The boot closure runs against a real `ScopedFieldRegistry` and leaves it empty (`hasScopedProperties` returns false for all entity classes).
- Root `composer.json` updated.
- PHPStan + PHP-CS-Fixer clean.

## Implementation Notes
- The `it registers no scoped fields when the boot closure runs against a fresh ScopedFieldRegistry` test MUST construct a fresh `ScopedFieldRegistry` directly and invoke ONLY the `catalog-market` boot closure. Do NOT chain in `catalog-locale` (or any other bridge) — doing so would populate the same registry with `Product.name`/`Product.description` and the "registers no scoped fields" assertion would fail for the wrong reason. The simplest pattern: `$registry = new ScopedFieldRegistry(...); $boot = (require __DIR__ . '/../module.php')['boot']; $boot($registry); expect($registry->hasScopedProperties(Product::class))->toBeFalse();` — no `DependencyResolver` chain needed.
- Do NOT mirror the full `catalog-locale/tests/Feature/BootContributionTest.php` setup verbatim. Its bridge-chaining helpers are designed to verify a positive contribution; for a no-op bridge a direct-call test is both simpler and more correct.
