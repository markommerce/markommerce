# Task 021: Scaffold `markommerce/config-pgsql` package

**Status**: completed
**Depends on**: 006
**Retry count**: 0

## Description
Create the driver package skeleton mirroring `packages/scope-pgsql/`: `composer.json`, `module.php`, autoloading config (`Markommerce\Config\PgSql\` → `src/`), test scaffolding, and an empty bindings entry so the module is discoverable. No production code yet — that lands in tasks 022 and 023.

## Context
- Reference: `packages/scope-pgsql/composer.json` and `packages/scope-pgsql/module.php`
- Package name: `markommerce/config-pgsql`
- Required composer deps: `php >= 8.5`, `marko/core: self.version`, `marko/database-pgsql: self.version`, `markommerce/config: self.version`
- Autoload root: `Markommerce\Config\PgSql\` → `src/`
- Test autoload: `Markommerce\Config\PgSql\Tests\` → `tests/`
- Marko module flag: `"extra": { "marko": { "module": true } }`
- Root `composer.json`'s `require` block must include `markommerce/config-pgsql: self.version`

## Requirements (Test Descriptions)
- [x] `it has a composer.json declaring markommerce/config-pgsql with PHP 8.5 requirement`
- [x] `it declares marko-module type with the marko.module extra flag`
- [x] `it requires markommerce/config and marko/database-pgsql as self-version deps`
- [x] `it autoloads the Markommerce\Config\PgSql namespace from src/`
- [x] `it ships an empty module.php returning a valid bindings array`
- [x] `the root composer.json require block lists markommerce/config-pgsql: self.version`

## Acceptance Criteria
- `composer dump-autoload` succeeds at the workspace root
- The package is discoverable via Marko module discovery
- All files declare `declare(strict_types=1);`
- Directory tree matches: `composer.json`, `module.php`, `LICENSE`, `README.md` (stub), `src/`, `tests/Unit/`, `tests/Feature/`

## Implementation Notes
(Left blank — filled in by programmer)
