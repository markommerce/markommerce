# Task 009: Create `markommerce/catalog-locale` bridge package

**Status**: completed
**Depends on**: 002, 003, 007
**Retry count**: 0

## Description
Create the `markommerce/catalog-locale` package — a thin auto-wiring bridge whose entire purpose is a boot closure that registers `Product.name`, `Product.description`, `Category.name`, `Category.description` as locale-scoped fields against `ScopedFieldRegistry`. No PHP source classes, no entities, no components. Installing the bridge IS the configuration.

## Context
- Related files (new):
  - `packages/catalog-locale/composer.json` (requires `markommerce/catalog-scope`, `markommerce/locale`)
  - `packages/catalog-locale/module.php` (boot closure only)
  - `packages/catalog-locale/tests/Feature/BootContributionTest.php`
- Reference for the bridge pattern:
  - `packages/scope/tests/Feature/BridgeContributionTest.php` — already exercises the exact mechanism with a synthetic bridge module
  - `packages/scope/README.md` "Programmatic path (bridge `module.php`)" section
- Bridge shape:
  ```php
  return [
      'boot' => function (ScopedFieldRegistry $registry): void {
          foreach ([Product::class, Category::class] as $entityClass) {
              foreach (['name', 'description'] as $property) {
                  $registry->register(
                      entityClass: $entityClass,
                      property: $property,
                      axes: ['locale'],
                  );
              }
          }
      },
  ];
  ```
- Boot ordering: `DependencyResolver` sorts modules such that any module declaring `markommerce/scope` and `markommerce/locale` in `require` boots after both — so `ScopeRegistryInterface` already contains the `locale` axis when this bridge's boot runs.

## Requirements (Test Descriptions)
- [ ] `it registers Product.name as locale-scoped via ScopedFieldRegistry at boot`
- [ ] `it registers Product.description as locale-scoped via ScopedFieldRegistry at boot`
- [ ] `it registers Category.name as locale-scoped via ScopedFieldRegistry at boot`
- [ ] `it registers Category.description as locale-scoped via ScopedFieldRegistry at boot`
- [ ] `it does not register any field on entities other than Product and Category`
- [ ] `it requires markommerce/catalog-scope and markommerce/locale in composer.json`
- [ ] `it declares itself as a marko-module via composer extra.marko.module=true`
- [ ] `it auto-injects ScopedFieldRegistry into the boot closure via container::call`
- [ ] `it boots after markommerce/scope and markommerce/locale so the locale axis is registered before the bridge runs`

## Acceptance Criteria
- All requirements have passing tests.
- `packages/catalog-locale/` exists with composer.json + module.php + a boot-contribution feature test.
- The test fixture mirrors `BridgeContributionTest`'s setup pattern: a real `Container`, `DependencyResolver`, `runBootLoop()` helper.
- Code follows project standards.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
