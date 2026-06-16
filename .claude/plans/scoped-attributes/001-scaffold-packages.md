# Task 001: Scaffold `attribute-scope` + `catalog-attribute-scope` packages

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Create the two new scope-binding packages: `markommerce/attribute-scope` (kernel-level scoped option
labels) and `markommerce/catalog-attribute-scope` (product scoped attribute values). Composer,
`module.php` skeleton, Pest bootstrap, `src/`+`tests/`, root wiring. Loadable, testable shells.

## Context
- Mirror sibling scope-binding packages: STUDY `packages/catalog-scope/composer.json` and
  `packages/config-scope/composer.json`.
- `attribute-scope/composer.json`: `type: marko-module`, PSR-4 `Markommerce\AttributeScope\` → `src/`,
  tests `Markommerce\AttributeScope\Tests\`. Requires `php: ^8.5`, `markommerce/attribute: self.version`,
  `markommerce/scope: self.version`; require-dev `markommerce/testing`, `pestphp/pest: ^4.0`.
- `catalog-attribute-scope/composer.json`: PSR-4 `Markommerce\CatalogAttributeScope\` → `src/`. Requires
  `php: ^8.5`, `markommerce/catalog-attribute: self.version`, `markommerce/attribute: self.version`,
  `markommerce/scope: self.version`, `markommerce/catalog: self.version`. (NOT `catalog-scope` — soft
  integration only via the generic scope resolver.)
- Add BOTH packages to root `composer.json` `require` + test namespaces to root `autoload-dev` (mirror
  how `catalog-scope`/`config-scope` are wired). Add `.gitattributes` + `LICENSE` per siblings.

## Requirements (Test Descriptions)
- [x] `it autoloads a class from the Markommerce\AttributeScope namespace`
- [x] `it autoloads a class from the Markommerce\CatalogAttributeScope namespace`
- [x] `it marks attribute-scope as a marko module in composer extra`
- [x] `it marks catalog-attribute-scope as a marko module in composer extra`
- [x] `it declares markommerce/scope as a dependency of both packages`

## Acceptance Criteria
- Both packages autoload; `composer test` discovers their suites.
- Layout matches the `catalog-scope`/`config-scope` convention.

## Implementation Notes
- Created `packages/attribute-scope/` and `packages/catalog-attribute-scope/` with full package structure: `src/`, `tests/`, `composer.json`, `module.php`, `LICENSE`, `README.md`, `.gitattributes`, `tests/Pest.php`
- Placeholder classes: `AttributeScopeModule` (attribute-scope) and `CatalogAttributeScopeModule` (catalog-attribute-scope)
- Both added to root `composer.json` require block (alphabetically ordered) and `autoload-dev` psr-4 test namespaces
- Added `marko/testing` to require-dev (required by project ComposerJsonShapeTest standard for marko-modules with tests/)
- `catalog-attribute-scope` depends on `catalog-attribute`, `attribute`, `scope`, and `catalog` (not `catalog-scope`)
