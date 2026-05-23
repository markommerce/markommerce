# Task 001: Scaffold `markommerce/config` package

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the directory structure, `composer.json`, autoloading config, and an empty `module.php` for the new `markommerce/config` package. Set up Pest test scaffolding mirroring `packages/scope/`. No production code yet — this task only establishes the package skeleton so subsequent tasks can land files into a discoverable, autoloadable module.

## Context
- Pattern reference: `packages/scope/composer.json`, `packages/scope/module.php`, top-level layout
- New package name: `markommerce/config` (note: distinct from Marko's framework `marko/config` — namespace `Markommerce\Config\` makes them non-colliding in PHP, just adjacent in package naming)
- Required composer deps: `php >= 8.5`, `marko/core: self.version`, `marko/database: self.version`, `markommerce/scope: self.version`
- Marko module flag: `"extra": { "marko": { "module": true } }`
- Autoload: `Markommerce\Config\` → `src/`, test autoload: `Markommerce\Config\Tests\` → `tests/`
- The root `composer.json` already uses `"url": "packages/*"` as a path repository — new packages under `packages/` are auto-discovered, no repositories update needed
- The root `composer.json`'s `require` block must include `markommerce/config: self.version` (matching the existing entries for scope, scope-pgsql, catalog, etc.) so `composer install` pulls the new module

## Requirements (Test Descriptions)
- [ ] `it has a composer.json declaring markommerce/config with PHP 8.5 requirement`
- [ ] `it declares marko-module type with the marko.module extra flag`
- [ ] `it requires markommerce/scope as a self-version dependency`
- [ ] `it autoloads the Markommerce\Config namespace from src/`
- [ ] `it ships an empty module.php returning a valid bindings array`
- [ ] `it is discoverable via composer dump-autoload from the workspace root`
- [ ] `the root composer.json require block lists markommerce/config: self.version`

## Acceptance Criteria
- `composer dump-autoload` succeeds at the workspace root
- `composer test` runs (zero tests in this package yet is acceptable; package is just discoverable)
- Directory tree matches: `composer.json`, `module.php`, `LICENSE`, `README.md` (stub), `src/`, `tests/Unit/`, `tests/Feature/`
- The package is listed/loadable by Marko's module discovery
- All files declare `declare(strict_types=1);`

## Implementation Notes
(Left blank — filled in by programmer)
