# Task 001: Merge `scope-pgsql` → `scope` (+ retire the no-driver guard)

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Fold the `markommerce/scope-pgsql` driver package into `markommerce/scope` and delete it. Because scope's
Postgres renderer now ships in-package, retire the "no driver installed" machinery
(`NoDriverException` / `DRIVER_PACKAGES`) that exists only to nudge installing `scope-pgsql`.

## Context
- Driver src to MOVE (namespace `Markommerce\Scope\PgSql\` is unchanged — land under `packages/scope/src/PgSql/`):
  - `packages/scope-pgsql/src/Query/PgSqlScopedFieldRenderer.php`
  - `packages/scope-pgsql/src/Schema/ScopesGinIndexEmitter.php`
- Binding to fold into `packages/scope/module.php` (it already exists): `ScopedFieldRendererInterface::class => PgSqlScopedFieldRenderer::class`.
- Composer: `packages/scope/composer.json` — change `marko/database` → `marko/database-pgsql` (match the
  `self.version` style scope-pgsql used); confirm `scope`'s PSR-4 `Markommerce\Scope\ -> src/` already
  autoloads the moved `…\PgSql\` classes (it does).
- Tests: MOVE the meaningful driver tests into `packages/scope/tests/PgSql/...` keeping namespace
  `Markommerce\Scope\PgSql\Tests\`: `tests/Unit/Query/PgSqlScopedFieldRendererTest.php`,
  `tests/Unit/Schema/ScopesGinIndexEmitterTest.php`, `tests/Unit/AutoMigrationTest.php`,
  `tests/Feature/PostgresIntegrationTest.php`. NOTE: `AutoMigrationTest` is NOT scaffolding — it covers the
  scopes-column schema merge + JSONB/GIN emit behavior and scope core has NO equivalent test, so PRESERVE it
  (it references only `Marko\Database\*` + `Markommerce\Scope\Storage\HasScopes*`, all still valid). DROP only
  the genuine package-existence/scaffolding tests: `PackageScaffoldingTest`, `Unit/ReadmeTest`, `Unit/ModuleTest`,
  `Unit/CopiedTestTreeTest`, `Unit/SourceTree/SourceTreeTest` — they assert the now-deleted package's identity.
  - AUTOLOAD NOTE (VERIFIED): scope-pgsql has NO root `composer.json` `autoload-dev` entry for
    `Markommerce\Scope\PgSql\Tests\` (only `attribute-pgsql` has a root entry; see task 004). Pest discovers
    test files by directory (`packages/*/tests` in `phpunit.xml`), so the moved files run regardless. But the
    moved test files keep the `Markommerce\Scope\PgSql\Tests\` namespace, which does NOT fall under scope's
    package `autoload-dev` prefix `Markommerce\Scope\Tests\` → `tests/`. To keep these classes autoloadable by
    FQCN (Pest parallel can require this), ADD a root `composer.json` `autoload-dev.psr-4` entry
    `"Markommerce\\Scope\\PgSql\\Tests\\": "packages/scope/tests/PgSql/"` and land the files under
    `packages/scope/tests/PgSql/...`. Run `composer dump-autoload` after.
- RETIRE THE NO-DRIVER GUARD. VERIFIED SHAPE: `packages/scope/src/Exceptions/NoDriverException.php` holds a
  `private const array DRIVER_PACKAGES = ['markommerce/scope-pgsql']` + a `noDriverInstalled()` factory.
  IMPORTANT: `noDriverInstalled()` is **never actually called anywhere in scope's `src/`** (grep confirms no
  throw site) — it is already dead code that only exists to satisfy tests. So "retiring the guard" is purely:
  delete `NoDriverException.php` and remove every test reference to it. There is no runtime throw path to
  preserve and the renderer being bound by scope's own module makes the class moot. DELETE the class.
- UPDATE scope's OWN tests that reference `NoDriverException`/`DRIVER_PACKAGES`/`scope-pgsql` (VERIFIED — there
  are FOUR test files, not three; the plan previously missed `ScopedFieldExpressionTest`):
  1. `packages/scope/tests/Unit/ModulePhpTest.php`:
     - Line ~55 `it('does not bind ScopedFieldRendererInterface')` — this assertion now INVERTS: scope's
       module DOES bind it. Change to assert `$module['bindings']` toHaveKey `ScopedFieldRendererInterface`
       bound to `PgSqlScopedFieldRenderer`.
     - Line ~61 `it('...expects BindingException ... when ScopedFieldRendererInterface is unbound')` — this
       test boots a bare `Container` with no bindings, so it still throws `BindingException`; it remains
       valid as-is (the interface is only bound via scope's module, not in this bare container). KEEP but
       drop any comment implying a driver package must supply it.
     - Line ~73 `it('...NoDriverException::noDriverInstalled() suggestion mentions markommerce/scope-pgsql')`
       — DELETE this test (the class is gone). Remove the `use ...NoDriverException;` import.
  2. `packages/scope/tests/Unit/SourceTree/SourceTreeTest.php`: remove `NoDriverException::class` from the
     autoload-list test (~line 34), DELETE the two NoDriver tests (~line 172 `DRIVER_PACKAGES contains
     markommerce/scope-pgsql` and ~line 194 `noDriverInstalled()->getSuggestion() mentions ...scope-pgsql`),
     remove the `use ...NoDriverException;` import, and update the `scope-pgsql` directory-scan test (~line
     206) which lists `$packagesRoot . '/scope-pgsql/src'` and `/scope-pgsql/tests` — those dirs no longer
     exist; drop them (the `is_dir()` guard makes it pass either way, but remove for clarity).
  3. `packages/scope/tests/Unit/Query/ScopedFieldExpressionTest.php` (PREVIOUSLY MISSED): remove the
     `use ...NoDriverException;` import (~line 5), DELETE the `it('...NoDriverException::noDriverInstalled()
     message and context reference ScopedFieldRendererInterface...')` test (~line 81), and update the
     `scope-pgsql` directory-scan test (~line 18) that lists `$packagesDir . '/scope-pgsql'`. Also check
     `expect(...)->toThrow(NoDriverException::class)` at ~line 34 — re-target it to the real exception the
     code throws (verify what the renderer/expression actually throws; it is NOT NoDriverException at
     runtime), or delete the assertion if it was only asserting the dead class.
  4. `packages/scope/tests/Unit/ReadmeTest.php`: update the "driver install line for markommerce/scope-pgsql"
     expectation in lockstep with the README edit below.
- README: `packages/scope/README.md` — replace the "install markommerce/scope-pgsql driver" line with a note
  that scope ships its Postgres implementation directly (update ReadmeTest's expectation in lockstep).
- Root `composer.json`: remove the `markommerce/scope-pgsql` `require` entry and its path `repositories` entry.
- Delete `packages/scope-pgsql/`. Run `composer dump-autoload`.
- Cross-package: `packages/catalog-storefront/tests/Unit/Tier1CompileTest.php` asserts the profile
  `not->toContain('markommerce/scope-pgsql')` — that stays true; verify it still passes (no scope-pgsql in
  any profile). Grep the repo for any other `scope-pgsql` references and fix.

## Requirements (Test Descriptions)
- [x] `it binds ScopedFieldRendererInterface to PgSqlScopedFieldRenderer from scope's own module` (inverts the old `does not bind` assertion)
- [x] `it renders scoped field SQL via the in-package PgSqlScopedFieldRenderer` (moved test, still green)
- [x] `it emits the scopes GIN index from the in-package emitter` (moved test, still green)
- [x] `it no longer references markommerce/scope-pgsql anywhere in scope sources, tests, or composer`
- [x] `it has deleted NoDriverException and removed every test reference to it` (class was dead code — never thrown)
- [x] `it autoloads the moved Markommerce\Scope\PgSql\Tests\ classes via the new root autoload-dev entry`

## Acceptance Criteria
- `packages/scope-pgsql/` deleted; renderer + emitter live under `packages/scope/src/PgSql/`; scope `module.php`
  binds the renderer; no-driver machinery retired; root composer cleaned; `composer dump-autoload` clean.
- scope unit + Feature suites green; phpcs + phpstan level 8 clean for `packages/scope`.
- No remaining `markommerce/scope-pgsql` reference in the repo.

## Implementation Notes
- `packages/scope-pgsql/` deleted; renderer + emitter moved to `packages/scope/src/PgSql/Query/` and `packages/scope/src/PgSql/Schema/` preserving their `Markommerce\Scope\PgSql\` namespaces.
- `packages/scope/module.php` updated to bind `ScopedFieldRendererInterface::class => PgSqlScopedFieldRenderer::class`.
- `packages/scope/composer.json` changed `marko/database` → `marko/database-pgsql`.
- `packages/scope/src/Exceptions/NoDriverException.php` deleted (was dead code — never called from `src/`).
- Tests moved to `packages/scope/tests/PgSql/` (Unit/Query, Unit/Schema, Unit/AutoMigration, Feature/PostgresIntegration). Scaffolding tests dropped.
- Root `composer.json`: removed `markommerce/scope-pgsql` require; added `Markommerce\\Scope\\PgSql\\Tests\\` autoload-dev entry pointing to `packages/scope/tests/PgSql/`.
- `composer.lock` updated to remove scope-pgsql entry and update scope's dependency from `marko/database` to `marko/database-pgsql`.
- Fixed scope's own tests (`ModulePhpTest`, `ScopedFieldExpressionTest`, `SourceTreeTest`, `ReadmeTest`, `PackageScaffoldingTest`) to remove all NoDriverException and scope-pgsql references.
- Added 3 structural-assertion tests in `SourceTreeTest.php` covering requirements 4, 5, 6.
- Updated `FEATURES.md` to remove scope-pgsql from package inventory and Tier 2 stack description.
- Pre-existing FEATURES.md test failures (package count assertions for P5 plan) are unrelated to this task — they were already failing before.
