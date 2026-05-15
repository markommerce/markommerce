# Task 003: Scaffold markommerce/frontend Composer package

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description

Create the PHP-side skeleton for `markommerce/frontend`: a new `packages/frontend/` directory containing `composer.json` (type `marko-module`), an empty `module.php`, `src/` namespaced under `Markommerce\Frontend\`, `tests/` for Pest, and a `.gitkeep` in `src/` so the empty directory commits. No business logic yet — Latte extension wiring lands in task 013/014, demo wiring in task 018. Register the package in the repo-root `composer.json` as a `self.version` requirement.

## Context

- Reference pattern: `marko/vite`'s `composer.json` at `/home/michal/www/marko/marko/packages/vite/composer.json` (the only Marko package on disk that uses the exact shape we need: `type: marko-module`, `extra.marko.module: true`, `self.version` deps). The existing `packages/core/composer.json` in this repo uses `type: library` and is a placeholder — do NOT mirror it.
- Composer name: `markommerce/frontend`. Namespace: `Markommerce\Frontend\`. Type: `marko-module`.
- Required Composer deps: `php: ^8.5`, `marko/core: self.version`, `marko/vite: self.version`, `marko/view: self.version`, `marko/view-latte: self.version`.
- Dev deps: `marko/testing: self.version`, `pestphp/pest: ^4.0`.
- `extra.marko.module: true` so Marko's `ModuleDiscovery` picks it up (verified in `marko/core/src/Module/ModuleDiscovery.php`).
- Related files: `/home/michal/www/marko/marko/packages/vite/composer.json` (pattern reference), repo-root `composer.json` (requires update — add `"markommerce/frontend": "self.version"`).

## Requirements (Test Descriptions)

- [x] `it has a composer.json declaring name markommerce/frontend and type marko-module`
- [x] `it requires marko/core, marko/vite, marko/view, marko/view-latte all at self.version`
- [x] `it autoloads Markommerce\\Frontend\\ from src/`
- [x] `it autoloads Markommerce\\Frontend\\Tests\\ from tests/`
- [x] `it sets extra.marko.module to true`
- [x] `it has an empty module.php returning []`
- [x] `it has placeholder src/ and tests/Unit/ directories`
- [x] `the repo-root composer.json now requires markommerce/frontend at self.version`
- [x] `composer validate exits 0 on the new package manifest`

## Acceptance Criteria

- `composer install` (or `composer update --no-install`) resolves the new package via the existing `packages/*` path repository.
- No PHPStan or PHPCS errors on the empty scaffold.
- `./vendor/bin/pest packages/frontend/tests/` runs (and reports zero tests, not an error).

## Implementation Notes

- Created `packages/frontend/composer.json` using `marko/vite` as the reference pattern: `type: marko-module`, `extra.marko.module: true`, all deps at `self.version`.
- Created `packages/frontend/module.php` returning `[]` — no bindings yet; Latte wiring lands in task 013/014.
- Created `packages/frontend/src/.gitkeep` (empty `src/`) and `packages/frontend/tests/Unit/` (already exists from the test file).
- Updated root `composer.json` to add `"markommerce/frontend": "self.version"` to `require`.
- `composer update markommerce/frontend --no-scripts` resolved the symlink successfully.
- All 9 tests pass; PHPStan level 8 clean; PHPCS clean.
- Pre-existing `ContentSkeletonTest` failure (unrelated to this task) was present before these changes.
