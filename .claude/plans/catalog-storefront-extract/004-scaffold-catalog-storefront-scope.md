# Task 004: Scaffold `markommerce/catalog-storefront-scope` package

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the empty `markommerce/catalog-storefront-scope` package skeleton. This is the sibling bridge that will own `ScopedProductGridComponent` after task 005 moves it from `catalog-scope`. The package requires both `markommerce/catalog-storefront` (for `ProductGridComponent` and `ProductGridData`) and `markommerce/catalog-scope` (for `ProductScopedOverrides` and the scoped-field machinery).

Same shape as task 001: composer.json, LICENSE, .gitattributes, src/.gitkeep, tests/Pest.php, root composer.json wiring.

## Context
- Reference: `packages/catalog-locale/` is a similarly tiny bridge package (P2 task 009). Follow the same skeleton.
- Composer require: `php ^8.5`, `marko/core` (for the `#[Preference]` attribute the moved component carries), `markommerce/catalog-scope`, `markommerce/catalog-storefront`.
- Composer require-dev: `marko/testing`, `pestphp/pest ^4.0`.
- Autoload PSR-4: `Markommerce\\CatalogStorefrontScope\\` → `src/`.
- Autoload-dev: `Markommerce\\CatalogStorefrontScope\\Tests\\` → `tests/`.
- `extra.marko.module = true`.
- Root `composer.json`: add `markommerce/catalog-storefront-scope` to `require` and `Markommerce\\CatalogStorefrontScope\\Tests\\` to `autoload-dev.psr-4`.
- Do NOT add a `module.php`. The Preference attribute on the moved component is auto-discovered by `PreferenceDiscovery`.

## Requirements (Test Descriptions)
- [ ] `it creates packages/catalog-storefront-scope/composer.json declaring markommerce/catalog-storefront-scope as a marko-module with the correct require list`
- [ ] `it declares Markommerce\\CatalogStorefrontScope\\ PSR-4 autoload mapping to src/`
- [ ] `it declares Markommerce\\CatalogStorefrontScope\\Tests\\ PSR-4 autoload-dev mapping to tests/`
- [ ] `it requires both markommerce/catalog-storefront and markommerce/catalog-scope in composer.json`
- [ ] `it creates packages/catalog-storefront-scope/LICENSE, .gitattributes, src/.gitkeep, and tests/Pest.php matching the project scaffolding conventions`
- [ ] `it adds markommerce/catalog-storefront-scope to the root composer.json require block alphabetically within the markommerce/* group`
- [ ] `it adds Markommerce\\CatalogStorefrontScope\\Tests\\ to the root composer.json autoload-dev.psr-4 block`
- [ ] `it passes composer validate on packages/catalog-storefront-scope/composer.json`

## Acceptance Criteria
- All requirements have passing tests in `packages/catalog-storefront-scope/tests/PackageScaffoldingTest.php`.
- `composer dump-autoload` at monorepo root succeeds.
- No source files yet (`src/.gitkeep` only).
- Code follows project standards.

## Implementation Notes
(Left blank — filled in by programmer during implementation.)
