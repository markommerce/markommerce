# Task 003: Merge `config-scope-pgsql` → `config-scope`

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Fold the `markommerce/config-scope-pgsql` driver into `markommerce/config-scope` and delete it.

## Context
- Depends on 002 only for serialization on root `composer.json`.
- Driver src to MOVE (namespace `Markommerce\ConfigScope\PgSql\` unchanged → `packages/config-scope/src/PgSql/`):
  - `packages/config-scope-pgsql/src/PgsqlScopedConfigStorage.php` → `config-scope/src/PgSql/PgsqlScopedConfigStorage.php`
  - `packages/config-scope-pgsql/src/Schema/ConfigValueOverridesTableEmitter.php` → `config-scope/src/PgSql/Schema/...`
  - `packages/config-scope-pgsql/src/Entity/ConfigValueOverrideRecord.php` → `config-scope/src/PgSql/Entity/...`
    — SAME ENTITY-DISCOVERY ISSUE as task 002's `ConfigValueRecord`: after moving under `src/PgSql/Entity`,
    the harness (`resolveEntityDirs()` scans `{path}/src/Entity` only) stops provisioning
    `config_value_overrides`. Task 002 lands the `resolveEntityDirs()` change to also scan `{path}/src/PgSql/Entity`;
    this task RELIES on that already being in place (dep on 002 is therefore a real code dependency, not just
    serialization). If somehow not yet applied, apply it here too. Verify with `composer test:integration`.
- Binding to fold into `packages/config-scope/module.php` (exists): `ScopedConfigStorageInterface::class =>
  closure building `new PgsqlScopedConfigStorage($container->get(ConnectionInterface::class))`` (verbatim).
- Composer: `packages/config-scope/composer.json` — add/bump to `marko/database-pgsql` (it currently depends
  on `config` + `scope`; add the framework pgsql driver the moved code needs). Match `self.version` style.
- Tests: MOVE `tests/Feature/PgsqlScopedConfigStorageTest.php`,
  `tests/Feature/ConfigValueOverridesTableEmitterTest.php`,
  `tests/Unit/Entity/ConfigValueOverrideRecordTest.php` into `packages/config-scope/tests/PgSql/...` (keep
  namespace `Markommerce\ConfigScope\PgSql\Tests\`). DROP `PackageScaffoldingTest`.
  - AUTOLOAD NOTE: config-scope-pgsql has NO root `autoload-dev` entry today (verified). Moved tests keep
    `Markommerce\ConfigScope\PgSql\Tests\`, outside config-scope's `Markommerce\ConfigScope\Tests\` → `tests/`
    prefix. ADD a root `composer.json` `autoload-dev.psr-4` entry
    `"Markommerce\\ConfigScope\\PgSql\\Tests\\": "packages/config-scope/tests/PgSql/"`. NOTE: this directly
    CONTRADICTS the existing assertion in `packages/config/tests/Unit/ComposerManifestTest.php` (~lines
    196-208) `it('does NOT add Markommerce\ConfigScope\PgSql\Tests\ to root autoload-dev ...')`. That test
    must be DELETED/INVERTED here — its premise (the namespace is declared only in the deleted package's local
    composer) is gone. Replace it with an assertion that the root DOES map
    `Markommerce\ConfigScope\PgSql\Tests\` → `packages/config-scope/tests/PgSql/`. `composer dump-autoload` after.
- If `config-scope` has a "no storage driver bound" guard mirroring config/scope, retire it (the storage is
  now always bound in-package).
- Root `composer.json`: remove `markommerce/config-scope-pgsql` `require` + path `repositories` entries.
- Delete `packages/config-scope-pgsql/`. `composer dump-autoload`.
- Cross-package + assertion fixes (all break the moment config-scope-pgsql is deleted — fix in THIS task):
  - `packages/config-scope/tests/Feature/Tier2EndToEndTest.php` — `tier2BuildManifests()` calls
    `resolveFrom(['markommerce/config-scope-pgsql', 'markommerce/config-pgsql', ...])`; repoint
    `config-scope-pgsql` → `config-scope` (config-pgsql was already repointed in task 002). The imports
    `Markommerce\ConfigScope\PgSql\Schema\ConfigValueOverridesTableEmitter` and
    `Markommerce\Config\PgSql\Schema\ConfigValuesTableEmitter` are UNCHANGED FQCNs (namespaces preserved) — they
    still resolve; only the resolveFrom names change.
  - `packages/testing/tests/Unit/Container/ContainerBootstrapperTest.php` — `buildConfigScopePgsqlManifests()`
    still lists `markommerce/config-scope-pgsql` after task 002 repointed config-pgsql. Repoint
    `config-scope-pgsql` → `config-scope` here. (Rename the helper too if desired; not required.)
  - `packages/config/tests/Unit/ComposerManifestTest.php`:
    - Lines ~103-115 `it('adds markommerce/config-scope-pgsql to root composer.json require block')` asserts
      root REQUIRES `markommerce/config-scope-pgsql`. INVERTS — the package is gone. DELETE this test (or
      replace with an assertion that root no longer requires it). The companion `config-scope` assertion
      (~89-101) stays valid.
    - Lines ~48-57 `it('asserts packages/config/composer.json does NOT require markommerce/config-scope-pgsql')`
      stays trivially TRUE (config never required it). KEEP as-is.
    - Lines ~196-208 (the `does NOT add ...PgSql\Tests\ to root autoload-dev` assertion) — handled in the
      AUTOLOAD NOTE above (delete/invert).
- Grep the repo for any remaining `config-scope-pgsql` reference and fix.

## Requirements (Test Descriptions)
- [x] `it binds ScopedConfigStorageInterface to PgsqlScopedConfigStorage from config-scope's own module`
- [x] `it persists and reads scoped config overrides via the in-package storage` (moved Feature test, green)
- [x] `it provisions the config value overrides table from the in-package emitter` (moved Feature test, green)
- [x] `it no longer references markommerce/config-scope-pgsql anywhere in sources, tests, or composer`

## Acceptance Criteria
- `packages/config-scope-pgsql/` deleted; code under `packages/config-scope/src/PgSql/`; binding folded;
  ComposerManifestTest assertion updated; root composer cleaned.
- config-scope unit + Feature suites green; phpcs + phpstan level 8 clean for `packages/config-scope`.
- No remaining `markommerce/config-scope-pgsql` reference in the repo.

## Implementation Notes
- Moved src files from `config-scope-pgsql/src/` to `config-scope/src/PgSql/` (namespaces unchanged).
- Folded `ScopedConfigStorageInterface` binding into `packages/config-scope/module.php`; added `ConnectionInterface` and `PgsqlScopedConfigStorage` imports.
- Added `marko/database-pgsql: self.version` to `packages/config-scope/composer.json` require.
- Moved test files to `packages/config-scope/tests/PgSql/`; dropped `PackageScaffoldingTest`.
- Added `Markommerce\\ConfigScope\\PgSql\\Tests\\ → packages/config-scope/tests/PgSql/` to root `autoload-dev`.
- Removed `markommerce/config-scope-pgsql` from root `composer.json` require; updated `composer.lock` manually.
- Updated `BootContributionTest.php`: moved test-double bindings to AFTER module loading (module now provides PgsqlScopedConfigStorage binding that must be overridden).
- Updated `Tier2EndToEndTest.php`: `resolveFrom` now uses `config-scope` instead of `config-scope-pgsql`.
- Updated `ContainerBootstrapperTest.php`: `buildConfigScopePgsqlManifests()` uses `config-scope` instead of `config-scope-pgsql`.
- Updated `ComposerManifestTest.php`: deleted stale "adds config-scope-pgsql to root require" test; inverted autoload-dev assertion.
- Updated `ConfigScopeDecouplePagesTest.php`: replaced README test with directory-deleted assertion.
- Added `SourceTreeTest.php` to verify no remaining `config-scope-pgsql` references in sources/tests/composer.
- FEATURES.md, docs/config-scope-pgsql.md, and config-scope/README.md references deferred to task 006.
