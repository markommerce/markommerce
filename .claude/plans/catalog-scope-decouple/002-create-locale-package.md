# Task 002: Create `markommerce/locale` axis package

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the `markommerce/locale` package as a pure axis-declaration module. It ships exactly one config file (`config/scope.php`) that contributes the `locale` axis to `scope.axes` via Marko's `ConfigDiscovery`. No PHP source classes, no module.php logic. The package is ~10 lines of contributed config plus a composer.json, a scaffolding test, and a README.

The package serves Tier 2 merchants: installing it makes the `locale` axis available in `ScopeRegistryInterface`. By itself it does nothing useful — `catalog-locale` (task 009) is what wires entities to the axis.

## Context
- Related files (new):
  - `packages/locale/composer.json`
  - `packages/locale/config/scope.php`
  - `packages/locale/tests/PackageScaffoldingTest.php`
  - `packages/locale/README.md` (created in task 012)
- The package has no `src/` directory (no PHP classes ship in P2). `extra.marko.module=true` plus a valid `composer.json` is enough for `ModuleDiscovery` to pick up the package; `EntityDiscovery` globs only `*/src/Entity` and will not look for entities here, which is correct.
- Patterns to follow:
  - `packages/scope-pgsql/composer.json` for composer manifest shape (`"type": "marko-module"`, `"extra.marko.module": true`)
  - `packages/scope/config/scope.php` for the config contribution shape
  - Marko's `ConfigDiscovery::discover()` (in `marko/packages/config/src/ConfigDiscovery.php` lines 19-37) iterates each module's `/config` directory and glob-loads `*.php` files, merging by filename via `ConfigMerger`. Dropping a `config/scope.php` here is sufficient — no PHP wiring needed.
  - `marko/packages/config/tests/Unit/ConfigDiscoveryTest.php` shows the canonical test wiring for multi-module config discovery.
- Reference for naming: FEATURES.md "Axis concept packages" — Requires `scope`.

## Requirements (Test Descriptions)
- [x] `it declares a locale axis with default 'default' and a single scope path 'default' in config/scope.php`
- [x] `it requires markommerce/scope in composer.json`
- [x] `it declares itself as a marko-module via composer extra.marko.module=true`
- [x] `it uses the Markommerce\Locale\ namespace for any future autoload (even though P2 ships no classes)`
- [x] `it merges its locale axis into scope.axes when discovered alongside scope's own config/scope.php`

## Acceptance Criteria
- All requirements have passing tests.
- New package exists at `packages/locale/` with composer.json, config/scope.php, and one scaffolding test.
- The root `composer.json` is updated under task 006 to add `markommerce/locale` to `require` and `Markommerce\Locale\Tests\` to `autoload-dev.psr-4`. Do not duplicate that work here.
- `composer dump-autoload` succeeds for the package.
- Code follows project standards.

## Implementation Notes
- Created `packages/locale/` with: `composer.json`, `config/scope.php`, `tests/PackageScaffoldingTest.php`, `tests/Pest.php`, `LICENSE`, `.gitattributes`, and a stub `README.md`.
- No `src/` directory — package ships no PHP classes in this phase.
- The config/scope.php contributes only the `locale` axis; merging alongside `packages/scope/config/scope.php` via `ConfigDiscovery` adds `locale` to the existing `market` and `channel` axes.
- Package-standard compliance files (LICENSE, .gitattributes, tests/Pest.php) added to satisfy existing monorepo test suite requirements.
- Root `composer.json` changes deferred to Task 006 as specified.
