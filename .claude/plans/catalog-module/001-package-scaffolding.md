# Task 001: Package Scaffolding

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `markommerce/catalog` package skeleton: `composer.json`, a placeholder `module.php`, and the Pest test bootstrap. This is a single package containing both the catalog domain layer and the storefront controller.

## Context
- New package directory: `packages/catalog/`.
- Model `composer.json` on `packages/scope/composer.json` and `packages/frontend-demo/composer.json`.
- The package depends on the scope module, the database layer, and (for the storefront controller in later tasks) the routing/view/layout stack.
- Related files to create:
  - `packages/catalog/composer.json`
  - `packages/catalog/module.php` — returns an empty array `[]` for now (bindings added in task 010)
  - `packages/catalog/tests/Pest.php` — model on `packages/scope/tests/Pest.php`
- `composer.json` requires (use `self.version` for marko/markommerce packages, matching sibling packages): `php` `^8.5`, `marko/core`, `marko/config`, `marko/database`, `marko/routing`, `marko/view`, `marko/view-latte`, `marko/layout`, `markommerce/scope`, `markommerce/frontend`, `markommerce/theme-blank`.
- `require-dev`: `pestphp/pest` `^4.0`, `marko/testing`.
- `autoload` PSR-4: TWO entries —
  - `Markommerce\Catalog\` → `src/`
  - `Markommerce\Catalog\Seed\` → `Seed/` — REQUIRED. The catalog seeder (task 012) must live at `packages/catalog/Seed/` (a sibling of `src/`) because `marko/database`'s `SeederDiscovery` only globs `vendor/*/*/Seed`, never `src/Seed`. Without this PSR-4 entry the discovered seeder class is not autoloadable and the container cannot instantiate it.
- `autoload-dev` PSR-4: `Markommerce\Catalog\Tests\` → `tests/`.
- `extra.marko.module` = `true`; `type` = `marko-module`.

## Requirements (Test Descriptions)
- [x] `it defines the markommerce/catalog package with type marko-module`
- [x] `it autoloads the Markommerce\Catalog namespace from the src directory`
- [x] `it autoloads the Markommerce\Catalog\Seed namespace from the Seed directory`
- [x] `it autoloads the Markommerce\Catalog\Tests namespace from the tests directory`
- [x] `it requires markommerce/scope and marko/database`
- [x] `it requires the marko routing, view, view-latte and layout packages`
- [x] `it enables the marko module flag in composer extra`

## Acceptance Criteria
- All requirements have passing tests
- `composer.json` is valid JSON and parses
- Code follows code standards

## Implementation Notes
- Created `packages/catalog/composer.json` with all required dependencies, PSR-4 autoload entries (including `Markommerce\Catalog\Seed\` → `Seed/`), and `extra.marko.module: true`.
- Created `packages/catalog/module.php` returning an empty array `[]` as a placeholder.
- Created `packages/catalog/tests/Pest.php` modeled on `packages/scope/tests/Pest.php`.
- Created `packages/catalog/tests/Unit/PackageScaffoldingTest.php` with 7 tests covering all requirements.
- All 7 new tests pass; 3 pre-existing failures in other packages (composer validate tests) are unrelated to this task.
