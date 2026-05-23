# Task 001: Package Scaffolding

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `markommerce/layout` package skeleton: `composer.json`, `module.php`, directory structure, and the PSR-4 autoload wiring. This is the bootstrap task — all other tasks add files into this skeleton.

## Context
- New package at `packages/layout/` in the markommerce monorepo.
- Namespace: `Markommerce\Layout\` → `src/`, `Markommerce\Layout\Tests\` → `tests/`.
- Patterns to follow: `packages/catalog/composer.json` (markommerce module shape), `marko/layout`'s `composer.json` and `module.php`.
- `composer.json` must set `"type": "marko-module"` and `"extra": { "marko": { "module": true } }`.
- Required dependencies: `marko/core`, `marko/routing`, `marko/view`, `marko/cli` (all `self.version`). Dev: `marko/testing`, `pestphp/pest ^4.0`.
- `module.php` returns an array — leave `bindings`/`singletons` minimal for now (later tasks populate them); the file must exist and return a valid empty-ish structure.
- Create empty `.gitkeep`-style structure for `src/Contracts/`, `src/Source/`, `src/Operation/`, `src/Compiler/`, `src/Runtime/`, `src/Cache/`, `src/Discovery/`, `src/Command/`, `src/Middleware/`, `src/Exception/`, `tests/Unit/`, `tests/Feature/`.
- Add `var/` to the repo-root `.gitignore` — the compiled artifact lives at `var/cache/markommerce/layouts.php` and the project `.gitignore` currently has no `var/` entry. (`.gitignore` is at `/home/michal/www/marko/markommerce/.gitignore`.)

## Requirements (Test Descriptions)
- [x] `it has a composer.json declaring the markommerce/layout package`
- [x] `it registers as a marko module via the extra.marko.module flag`
- [x] `it autoloads the Markommerce\Layout namespace from src`
- [x] `it autoloads the Markommerce\Layout\Tests namespace from tests`
- [x] `it ships a module.php that returns an array`
- [x] `it declares marko/core, marko/routing, marko/view and marko/cli as dependencies`
- [x] `it adds the var directory to the repo .gitignore`

## Acceptance Criteria
- All requirements have passing tests
- `composer.json` validates (`composer validate`)
- Package follows markommerce package conventions
- Directory structure matches the Architecture Notes in `_plan.md`

## Implementation Notes
- Created `packages/layout/composer.json` with `marko-module` type, PSR-4 autoload, and all required dependencies (`marko/core`, `marko/routing`, `marko/view`, `marko/cli`)
- Created `packages/layout/module.php` returning a minimal `['bindings' => []]` array
- Created directory structure with `.gitkeep` files in all required subdirectories under `src/` and `tests/`
- Added `var/` to repo-root `.gitignore`
- Registered package via `composer require markommerce/layout:@dev` and updated root `composer.json` to use `self.version` constraint
- Added `Markommerce\\Layout\\Tests\\` to root `composer.json` autoload-dev
- Tests live at `packages/layout/tests/Unit/PackageScaffoldingTest.php`
