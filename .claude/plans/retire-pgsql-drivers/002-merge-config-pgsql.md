# Task 002: Merge `config-pgsql` → `config`

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Fold the `markommerce/config-pgsql` driver into `markommerce/config` and delete it. `config` now binds its
own Postgres storage, so relax the "storage comes from the driver package" assumption its tests encode.

## Context
- Depends on 001 only for serialization (both edit root `composer.json`); no code dependency.
- Driver src to MOVE (namespace `Markommerce\Config\PgSql\` unchanged → `packages/config/src/PgSql/`):
  - `packages/config-pgsql/src/PgsqlConfigStorage.php` → `packages/config/src/PgSql/PgsqlConfigStorage.php`
  - `packages/config-pgsql/src/Schema/ConfigValuesTableEmitter.php` → `packages/config/src/PgSql/Schema/ConfigValuesTableEmitter.php`
  - `packages/config-pgsql/src/Entity/ConfigValueRecord.php` → **SEE ENTITY-DISCOVERY NOTE BELOW.** The entity
    keeps namespace `Markommerce\Config\PgSql\Entity\ConfigValueRecord` and lands at
    `packages/config/src/PgSql/Entity/ConfigValueRecord.php` — BUT the testing harness only discovers entities
    under `{packagePath}/src/Entity`, so this move alone makes the harness stop provisioning `config_values`.
- ENTITY-DISCOVERY NOTE (CRITICAL — VERIFIED): `packages/testing/src/Profile/StoreProfile.php::resolveEntityDirs()`
  builds entity dirs as `{manifest->path}/src/Entity` ONLY, and `SchemaProvisioner` provisions tables from
  those dirs. Today `ConfigValueRecord` lives at `config-pgsql/src/Entity/`, so the harness discovers it and
  provisions the real `config_values` table for any profile that includes config-pgsql (e.g. `StoreProfile::storefront()`).
  After the move to `config/src/PgSql/Entity/`, the harness will NO LONGER find it → `config_values` is never
  provisioned → harness-booted integration tests that read DB-backed config break. FIX (coordinate with task
  005, which owns `resolveEntityDirs`): extend `resolveEntityDirs()` to ALSO add `{path}/src/PgSql/Entity`
  when it exists. This task must land that one-line `resolveEntityDirs` change (or do it as the first step of
  this task) so the suite is green at task end — do NOT defer it to 005, because config-pgsql is deleted here.
  Verify with `composer test:integration` for the catalog-storefront `storefront()`-profile tests at task end.
- Binding to fold into `packages/config/module.php` (exists): `ConfigStorageInterface::class => closure
  building `new PgsqlConfigStorage($container->get(ConnectionInterface::class))`` (copy verbatim from
  `config-pgsql/module.php`).
- Composer: `packages/config/composer.json` — `marko/database` → `marko/database-pgsql`.
- Tests: MOVE `tests/Feature/PgsqlConfigStorageTest.php`, `tests/Feature/ConfigValuesTableEmitterTest.php`,
  `tests/Unit/Entity/ConfigValueRecordTest.php`, `tests/Unit/StorageUnaffectedByEntityTest.php` into
  `packages/config/tests/PgSql/...` (keep namespace `Markommerce\Config\PgSql\Tests\`). DROP
  `PackageScaffoldingTest` + config-pgsql's `Unit/ReadmeTest`.
  - AUTOLOAD NOTE: like scope, config-pgsql has NO root `autoload-dev` entry today. The moved tests keep
    `Markommerce\Config\PgSql\Tests\`, which does not fall under config's `Markommerce\Config\Tests\` →
    `tests/` prefix. ADD a root `composer.json` `autoload-dev.psr-4` entry
    `"Markommerce\\Config\\PgSql\\Tests\\": "packages/config/tests/PgSql/"` and land files under
    `packages/config/tests/PgSql/...`. `composer dump-autoload` after.
- RELAX THE ASSUMPTION (CORRECTED — VERIFIED): `packages/config/tests/Unit/ModulePhpTest.php` does NOT contain
  an assertion like `$module['bindings']->not->toHaveKey(ConfigStorageInterface::class)`. What it actually has
  (lines ~125-128) is a CODE COMMENT ("the config module itself no longer provides a default binding") plus a
  manual `$container->bind(ConfigStorageInterface::class, InMemoryConfigStorage::class)` test double. So there
  is no failing assertion to "invert." Do this instead: (a) update the comment to say config now binds
  `ConfigStorageInterface` to `PgsqlConfigStorage` via its own module; (b) the manual `InMemoryConfigStorage`
  bind can stay (it's a deliberate unit test double so these tests don't need a real DB) — keep it, but verify
  it still wins over / coexists with the module binding when `bootModuleContainer` later registers
  `$moduleArray['bindings']` (the manual bind happens BEFORE the loop at line ~131, so the module's binding
  will OVERWRITE it; if any test depends on `InMemoryConfigStorage` being resolved, move the manual bind to
  AFTER the bindings loop, or skip binding `ConfigStorageInterface` from the module in that test harness path);
  (c) ADD a positive assertion: `it('binds ConfigStorageInterface to PgsqlConfigStorage from config's own module')`.
  config has NO runtime "no storage bound" guard (verified — nothing to retire).
- Root `composer.json`: remove `markommerce/config-pgsql` `require` + path `repositories` entries.
- Delete `packages/config-pgsql/`. `composer dump-autoload`.
- Cross-package profile lists / hard breakers to repoint config-pgsql → config (drop if config already
  present). ALL of these reference the deleted package or its deleted dir and WILL break the suite the moment
  config-pgsql is removed, so they MUST be fixed inside this task (the serialization-greenness contract
  requires it — none can be deferred to 005):
  - `packages/catalog-storefront/tests/Unit/Tier1CompileTest.php` — line ~107 constructs a `ModuleManifest`
    with `path: $pkgRoot . '/config-pgsql'` (a deleted dir) and line ~192 asserts the profile CONTAINS
    `markommerce/config-pgsql`. Change both to `markommerce/config` / `config`.
  - `packages/config-scope/tests/Feature/Tier2EndToEndTest.php` — `tier2BuildManifests()` calls
    `ModuleResolver::resolveFrom(['markommerce/config-scope-pgsql', 'markommerce/config-pgsql', ...])`; repoint
    `config-pgsql` → `config` here (config-scope-pgsql is handled in task 003). It imports
    `Markommerce\Config\PgSql\Schema\ConfigValuesTableEmitter` — that FQCN is UNCHANGED by the move (namespace
    preserved), so the import still resolves. Just fix the resolveFrom package name.
  - `packages/catalog-attribute-storefront/tests/Feature/CategoryPageRenderTest.php` (lists `markommerce/config-pgsql`).
  - `packages/testing/tests/Unit/Container/ContainerBootstrapperTest.php` — `buildConfigScopePgsqlManifests()`
    (~line 98-103) calls `resolveFrom(['markommerce/config-scope-pgsql', 'markommerce/config-pgsql'])`. Repoint
    `config-pgsql` → `config` here; config-scope-pgsql in task 003. (If left, `ModuleResolver` throws on the
    missing package and the testing suite goes RED.)
  - `packages/testing/src/Profile/StoreProfile.php` — `storefront()` preset (line ~147) lists
    `markommerce/config-pgsql`; the doc comment (~line 134) names it. FIX HERE (`config-pgsql` → `config`):
    the suite must be green at task end and config-pgsql is gone. Task 005 only re-verifies StoreProfile is
    clean.
  - `packages/testing/tests/Unit/Profile/StoreProfileTest.php` — line ~48 asserts the storefront profile
    `toContain('markommerce/config-pgsql')`. Change to `markommerce/config`. (Breaks the moment StoreProfile
    drops config-pgsql above.)
- Grep the repo for any remaining `config-pgsql` reference and fix.

## Requirements (Test Descriptions)
- [x] `it binds ConfigStorageInterface to PgsqlConfigStorage from config's own module`
- [x] `it persists and reads config values via the in-package PgsqlConfigStorage` (moved Feature test, green)
- [x] `it provisions the config_values table from the in-package emitter` (moved Feature test, green)
- [x] `it provisions config_values from a harness-booted storefront profile after the entity moved under src/PgSql/Entity` (resolveEntityDirs now scans src/PgSql/Entity)
- [x] `it lists markommerce/config (not config-pgsql) in the StoreProfile storefront preset`
- [x] `it no longer references markommerce/config-pgsql anywhere in config sources, tests, composer, StoreProfile, or the testing-package tests`

## Acceptance Criteria
- `packages/config-pgsql/` deleted; storage + entity + emitter under `packages/config/src/PgSql/`; config
  `module.php` binds storage; ModulePhpTest assertion inverted; root composer cleaned.
- config unit + Feature suites green; phpcs + phpstan level 8 clean for `packages/config`.
- No remaining `markommerce/config-pgsql` reference in the repo.

## Implementation Notes
- Moved all driver sources preserving namespaces: `PgsqlConfigStorage`, `ConfigValuesTableEmitter`, `ConfigValueRecord` → `packages/config/src/PgSql/`
- Folded binding into `packages/config/module.php`; changed dep from `marko/database` to `marko/database-pgsql`
- Moved all tests to `packages/config/tests/PgSql/`; added `Markommerce\\Config\\PgSql\\Tests\\` autoload-dev entry in root `composer.json`
- Extended `StoreProfile::resolveEntityDirs()` to also scan `{path}/src/PgSql/Entity` so `config_values` gets provisioned
- Stale Postgres template DBs (built before the entity-discovery fix) had to be dropped manually so templates were recreated with the correct schema
- All cross-package `config-pgsql` references updated; `packages/config-pgsql/` deleted
- `composer test`: 2300 passed; `composer test:integration`: 167 passed; `phpcs`: clean; `phpstan level 8`: no errors
