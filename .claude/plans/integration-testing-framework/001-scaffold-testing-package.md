# Task 001: Scaffold the `markommerce/testing` package

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the new `markommerce/testing` package skeleton: directory, `composer.json`, PSR-4 autoload, and wire it into the monorepo's test setup so other packages can `require-dev` it. It is a plain composer library — NOT a marko module (it must never boot in production); do NOT set `extra.marko.module`.

## Context
- New dir: `packages/testing/` with `src/`, `tests/Unit/`, `tests/Feature/`, `tests/Pest.php`, `composer.json`.
- Namespace `Markommerce\Testing\` → `src/`. Mirror an existing package `composer.json` (e.g. `packages/catalog/composer.json`) for structure/version conventions, but require marko/database + marko/core + marko/database-pgsql (it consumes `EntityDiscovery`, `SchemaRegistry`, `PgSqlGenerator`, `PgSqlConnection`, `Container`, `DependencyResolver`, `PreferenceDiscovery`, `ConnectionInterface`). Also require pest/phpunit as dev where appropriate.
- **No-cycle invariant**: `markommerce/testing` MUST NOT `require` (or `require-dev`) any markommerce MODULE package (catalog, config, scope, etc.). It resolves modules dynamically at runtime from `vendor/composer/installed.json`. Modules depend on testing (one direction only: module → testing as require-dev), never the reverse. Keep its dependencies to marko framework packages + test tooling.
- Register the package as a path repository so the monorepo autoloads it (check root `composer.json` repositories/autoload + how other packages are wired). Confirm it appears in `vendor/composer/installed.json` after `composer dump-autoload`/install.
- Root `phpunit.xml` already globs `packages/*/tests` — confirm `packages/testing/tests` is picked up.

## Requirements (Test Descriptions)
- [x] `it autoloads a class from the Markommerce\Testing namespace`
- [x] `it exposes the package on the test suite path` (a trivial Unit test under packages/testing/tests/Unit runs)
- [x] `it is not registered as a marko module` (composer.json has no extra.marko.module === true)

## Acceptance Criteria
- `packages/testing/composer.json` valid; `Markommerce\Testing\` PSR-4 → `src/`.
- A placeholder class (e.g. `Markommerce\Testing\Testing` marker or the first real class) autoloads.
- `composer dump-autoload` succeeds; package resolvable from other packages.
- PHPStan level 8 clean on the new package (run with `php -d memory_limit=2G`).

## Implementation Notes

- Created `packages/testing/` with `src/`, `tests/Unit/`, `tests/Feature/`, `tests/Pest.php`, `composer.json`.
- Package type is `library` (NOT `marko-module`); no `extra.marko.module` key.
- `Markommerce\Testing\Testing` marker class in `src/Testing.php` satisfies the autoload test.
- Root `composer.json`: added `markommerce/testing: self.version` to `require-dev` and `Markommerce\\Testing\\Tests\\` to `autoload-dev.psr-4`.
- Root `packages/*` wildcard path repo already covers the new package; ran `composer update markommerce/testing` to lock and symlink it.
- Package confirmed in `vendor/composer/installed.json`; PHPStan level 8 clean.
- Added `.gitattributes`, `LICENSE`, and `README.md` to satisfy the monorepo `PackageStandard` suite (existing tests that check every package has these files).
- No markommerce-module dependencies — only `marko/core`, `marko/database`, `marko/database-pgsql`.
