# Plan: Scope Fork From Marko

## Created
2026-05-18

## Status
completed

## Objective
Fork `marko/scope` and `marko/scope-pgsql` into `markommerce/scope` and `markommerce/scope-pgsql` (Postgres only, no MySQL) under the `Markommerce\Scope\…` namespace so the scope module can evolve independently of upstream `marko/scope`. Add both packages to the root project's `require` block as part of the concrete Markommerce stack. Behavior-identical copy — no API changes.

## Related Issues
none

## Discovery Notes

- Source packages live at `/home/michal/www/marko/marko/packages/scope/` (≈2000 LOC, src + tests) and `/home/michal/www/marko/marko/packages/scope-pgsql/` (≈320 LOC, src + tests). Both have full sentinel/scaffolding tests, README tests, and module bindings tests.
- Source docs are at `/home/michal/www/marko/marko/docs/src/content/docs/packages/scope.md` and `scope-pgsql.md`.
- Target paths are greenfield in markommerce: no existing `packages/scope*` or `docs/src/content/docs/packages/scope*.md`.
- Markommerce's root `composer.json` already lists `../marko/packages/*` as a path repository, so `marko/core`, `marko/config`, `marko/database`, `marko/database-pgsql` remain accessible without composer plumbing changes.
- The marko scope packages depend on these upstream marko/* internals (kept as-is): `marko/core` (Container), `marko/config` (ConfigRepository), `marko/database` (Entity, Repository, EntityHydrator, Attributes, QuerySpecification, EntityMetadataFactory, DiffCalculator, SchemaBuilder/Registry, IdentifierValidator), `marko/database-pgsql` (PgSqlGenerator).
- The only marko classes that get the rename are `Marko\Scope\…` and `Marko\Scope\PgSql\…` — everything else stays under `Marko\…`.
- The upstream `marko/scope` README references both `scope-mysql` AND `scope-pgsql` drivers. Since Markommerce ships Postgres only, the README and its sentinel test must drop the scope-mysql reference (the docs page's "Related Packages" section too).
- `extra.marko.module = true` is preserved in both composer.json files — that's what registers them as auto-loaded modules with `marko/core`'s discovery (see `marko/packages/core/src/Module/ModuleDiscovery.php` — discovery is automatic; no root-side manifest is required).
- The root composer.json should `require` (not `require-dev`) both new packages — they are part of the concrete Markommerce framework per the user's direction.
- **`NoDriverException::DRIVER_PACKAGES` hardcodes `'marko/scope-mysql'`** in `packages/scope/src/Exceptions/NoDriverException.php`. The rename must update this literal to `'markommerce/scope-pgsql'` so the loud-error suggestion points at the correct driver shipped by Markommerce.
- **`marko/core`'s `Container::get()` hardcodes a `Marko\\` prefix check** at `marko/packages/core/src/Container/Container.php:152` when auto-throwing `NoDriverException` for unbound interfaces. After our rename, `$container->get(Markommerce\Scope\Query\ScopeSortRendererInterface::class)` will throw `Marko\Core\Exceptions\BindingException::noImplementation()` instead of `Marko\Scope\Exceptions\NoDriverException`. The upstream `ModulePhpTest` test "throws a loud error if a ScopedOrderBy is used while no ScopeSortRendererInterface is bound" expects `NoDriverException` — it must be adapted in our fork to expect `BindingException` (since we don't modify upstream marko/core in this plan).
- **Inspection of `packages/scope/tests/Feature/`** (`ScopedOverridesPersistenceTest.php` and `ScopedOverridesEntityDirtyTrackingTest.php`) confirms neither test references `Marko\Scope\PgSql\…`; both use only in-memory `ConnectionInterface` fakes. No special grouping is needed.
- **`packages/scope-pgsql/tests/Feature/AutoMigrationTest.php` does NOT touch a real Postgres database.** It is a pure in-memory schema-diff test using `EntityMetadataFactory`, `SchemaRegistry`, `DiffCalculator`, and `PgSqlGenerator`. No `ConnectionInterface` is even constructed. Tagging it `integration-destructive` would be incorrect and would exclude a healthy test from the default run.
- **`packages/scope-pgsql/tests/Unit/ModuleTest.php` filters bindings by the literal string `'Marko\\Scope\\'`** in a `str_starts_with()` call. After rename to Markommerce, this literal would silently pass against an empty filtered set. It must be rewritten to `'Markommerce\\Scope\\'`. The accompanying test description "does not re-bind marko/scope interfaces" must also become "does not re-bind markommerce/scope interfaces".
- **Tests `tests/Unit/PackageScaffoldingTest.php` and `tests/Unit/ModulePhpTest.php` upstream** use double-backslashed namespace literals (`'Marko\\Scope\\'`) in PHP string assertions against `composer.json` autoload keys (which are double-backslashed because JSON requires it). After rename those assertion keys/values become `'Markommerce\\Scope\\'`. Grep guards in Task 007 must check BOTH single-backslash (PHP source code: `Marko\Scope`) AND double-backslash (PHP string literals, composer.json: `Marko\\Scope`) forms.
- No `tests/Pest.php` file exists in upstream `marko/scope-pgsql`. The package runs Pest fine without it; do not invent one in Task 004 unless `scope`'s `tests/Pest.php` is referenced by relative path (it is not).
- Neither `marko/scope` nor `marko/scope-pgsql` ships any tests tagged `->group(…)`. The plan should not promise to "preserve" upstream grouping — there is none.

## Scope

### In Scope
- Create `packages/scope/` with composer name `markommerce/scope`, namespace `Markommerce\Scope\`, full source and test parity with `marko/scope`.
- Create `packages/scope-pgsql/` with composer name `markommerce/scope-pgsql`, namespace `Markommerce\Scope\PgSql\`, full source and test parity with `marko/scope-pgsql`.
- Update both packages' `module.php` to bind the renamed FQCNs.
- Update each package's `PackageScaffoldingTest`, `ModulePhpTest`/`ModuleTest`, and `ReadmeTest` to assert the new composer name, namespace, install command, and (for scope) the single driver reference.
- Add `markommerce/scope` and `markommerce/scope-pgsql` to the root `composer.json` `require` block.
- Copy `docs/src/content/docs/packages/scope.md` and `scope-pgsql.md` from the marko docs into markommerce docs, adapted to the new package names / namespaces, dropping all `scope-mysql` references.
- Verify `composer test`, `phpcs`, and `phpstan analyse` are all clean under markommerce's existing toolchain inside Docker.

### Out of Scope
- Copying or porting `marko/scope-mysql` — Postgres only.
- Modifying `marko/scope` or `marko/scope-pgsql` in the marko monorepo. The fork is purely additive in the markommerce tree.
- API changes, new axes, new query renderers, integration with `markommerce/catalog` or any other markommerce module. Anything beyond a behavior-identical rename is a follow-up plan.
- Replacing `marko/core`, `marko/config`, or `marko/database` — Markommerce still depends on those.

## Success Criteria
- [ ] `packages/scope/` and `packages/scope-pgsql/` exist with full source, tests, README, module.php, .gitattributes, LICENSE, composer.json under the new names and namespaces.
- [ ] `Markommerce\Scope\…` and `Markommerce\Scope\PgSql\…` are the only top-level namespaces used by the new packages' own classes; all other `Marko\…` references in those classes (Config, Core, Database, Database\PgSql) remain.
- [ ] Root `composer.json` `require` block lists both new packages at `self.version`.
- [ ] `composer test` is fully green from the project root.
- [ ] `phpcs` (markommerce ruleset) reports zero issues across the new packages.
- [ ] `phpstan analyse` (level 8) reports zero issues across the new packages.
- [ ] `docs/src/content/docs/packages/scope.md` and `scope-pgsql.md` exist in the markommerce docs, mention only `markommerce/scope-pgsql` as the driver, and use `Markommerce\Scope\…` namespaces in all code examples.
- [ ] No PHP file in `packages/scope/` or `packages/scope-pgsql/` contains a reference to `Marko\Scope\…` in either single-backslash (source) or double-backslash (string literal / JSON key) form. Verified via grep.
- [ ] No string literal `marko/scope-mysql` or `marko/scope-pgsql` appears anywhere in `packages/scope/` or `packages/scope-pgsql/` (catches the `NoDriverException::DRIVER_PACKAGES` literal and any stray README/docstring leftovers).
- [ ] `NoDriverException::noDriverInstalled()->getSuggestion()` mentions `markommerce/scope-pgsql` (verifies the loud-error UX after the rename).
- [ ] **Self-containment**: imagining `marko/scope` and `marko/scope-pgsql` do not exist, `markommerce/scope` and `markommerce/scope-pgsql` continue to work exactly the same as the originals. Verified by all of: (a) `composer show marko/scope` and `composer show marko/scope-pgsql` both exit non-zero (the originals are not installed); (b) no `composer.json` under the project requires `marko/scope` or `marko/scope-pgsql`; (c) `composer.lock` contains neither in its `packages` or `packages-dev` arrays; (d) the marko-directories-aside smoke test in Task 007 confirms `composer test` still passes when the upstream sources are temporarily renamed aside.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Bootstrap `markommerce/scope` package skeleton (composer.json, LICENSE, .gitattributes, module.php, README.md, `PackageScaffoldingTest` + `Unit/ReadmeTest`; `Unit/ModulePhpTest` deferred to 003) | - | completed |
| 002 | Copy & rename `markommerce/scope` source tree (every `src/**/*.php`), including rewriting `NoDriverException::DRIVER_PACKAGES` to `['markommerce/scope-pgsql']` | 001 | completed |
| 003 | Copy & rename `markommerce/scope` test tree (every `tests/Unit/**` and `tests/Feature/**`, less the scaffolding tests already created in 001), AND re-author `Unit/ModulePhpTest.php` with the BindingException expectation rewrite plus the NoDriverException-suggestion assertion | 001, 002 | completed |
| 004 | Bootstrap `markommerce/scope-pgsql` package skeleton (composer.json, LICENSE, .gitattributes, module.php, README.md, scaffolding tests — including `Unit/ModuleTest` with the `'Markommerce\\Scope\\'` literal rewrite) | 001 | completed |
| 005 | Copy & rename `markommerce/scope-pgsql` source tree | 002, 004 | completed |
| 006 | Copy & rename `markommerce/scope-pgsql` test tree | 003, 005 | completed |
| 007 | Wire both packages into root `composer.json` `require`, run full `composer test` + `phpcs` + `phpstan` verification | 006 | completed |
| 008 | Adapt `docs/src/content/docs/packages/scope.md` for Markommerce | 007 | completed |
| 009 | Adapt `docs/src/content/docs/packages/scope-pgsql.md` for Markommerce | 007 | completed |

## Architecture Notes

- The new packages follow the same `marko-module` composer type as `markommerce/theme-blank` — they are picked up by `marko/core`'s module discovery via `extra.marko.module = true`.
- PSR-4 prefixes: `Markommerce\Scope\` → `packages/scope/src/`; `Markommerce\Scope\Tests\` → `packages/scope/tests/`; `Markommerce\Scope\PgSql\` → `packages/scope-pgsql/src/`; `Markommerce\Scope\PgSql\Tests\` → `packages/scope-pgsql/tests/`.
- The scope-pgsql composer.json `require` block lists `markommerce/scope` at `self.version` (mirroring the upstream pattern), plus the unchanged `marko/database-pgsql` and `marko/core` deps.
- The scope module.php bindings move from `Marko\Scope\…` FQCNs to `Markommerce\Scope\…`. Singletons and bindings remain conceptually identical: `ScopeRegistryInterface` → `PhpScopeRegistry`, plus singletons for `ScopeContext`, `ScopeMetadataFactory`, `ScopeResolver`, `ScopedOrderByFactory`, `ScopeWalker`.
- The scope-pgsql module.php binds `Markommerce\Scope\Query\ScopeSortRendererInterface` → `Markommerce\Scope\PgSql\Query\PgSqlScopeSortRenderer`.
- No `final` classes anywhere (preserve marko's existing non-final-ness — marko already conforms).
- The upstream `HasScopes` trait at `packages/scope/src/Storage/HasScopes.php` is preserved as a trait in our fork. The Markommerce "No traits" rule in `.claude/code-standards.md` is a coding standard for NEW Markommerce code; phpcs.xml does not enforce it, so keeping the trait causes no lint failures. The trait's behavior contract (the `$scopes` jsonb column + `setOverride()`/`override()`/`hasOverride()`/`clearOverride()`/`overrides()` methods) is part of the public API the fork must preserve. Converting it to composition would be a behavior-breaking refactor and is out of scope.
- Every file keeps `declare(strict_types=1);`.
- Linting and static analysis run inside Docker per `CLAUDE.local.md` — every verification command in this plan must use the `marko-playground-app` container.
- `tests/Feature/AutoMigrationTest.php` in scope-pgsql is purely in-memory (no PDO, no live DB connection) — it must stay in the default `composer test` run. Do NOT tag it `integration-destructive`. The same applies to `packages/scope/tests/Feature/Scoped*Test.php` — both use in-memory `ConnectionInterface` fakes only.

## Risks & Mitigations

- **Risk**: a stray `Marko\Scope\…` reference (e.g. inside a string, a `@throws` PHPDoc, or a phpcs/phpstan baseline comment) survives the rename and breaks autoloading or static analysis.
  - **Mitigation**: Task 007 explicitly greps for BOTH single-backslash (`Marko\Scope\` — PHP source/namespace/use) and double-backslash (`Marko\\Scope\\` — PHP/JSON string literals) forms in `packages/scope*/**/*.php` and `packages/scope*/composer.json`, asserting zero matches across both.
- **Risk**: `Marko\Core\Container::get()` short-circuits to a magic `Marko\<segment>\Exceptions\NoDriverException::noDriverInstalled()` only for ids starting with the literal prefix `Marko\\` (`marko/packages/core/src/Container/Container.php:152`). The renamed `Markommerce\Scope\Query\ScopeSortRendererInterface` will not match — `BindingException` is thrown instead.
  - **Mitigation**: Task 003 rewrites the upstream `ModulePhpTest` assertion `it('throws a loud error if a ScopedOrderBy is used while no ScopeSortRendererInterface is bound', …)` to expect `Marko\Core\Exceptions\BindingException` (still a loud, named exception — semantic intent preserved). The `NoDriverException` factory remains tested independently inside an `Exceptions\NoDriverExceptionTest` style assertion (we add a direct call to `NoDriverException::noDriverInstalled()` and verify the message/suggestion mention `markommerce/scope-pgsql`).
- **Risk**: `NoDriverException::DRIVER_PACKAGES` hardcodes `'marko/scope-mysql'`. A pure namespace rename leaves this stale string in place — the loud-error suggestion would tell users to install a package that doesn't exist in Markommerce.
  - **Mitigation**: Task 002 explicitly rewrites the `DRIVER_PACKAGES` array element from `'marko/scope-mysql'` to `'markommerce/scope-pgsql'` (single driver list). The Task 002 acceptance criteria assert this string is present and `marko/scope-mysql` is absent in `packages/scope/src/Exceptions/NoDriverException.php`.
- **Risk**: `packages/scope-pgsql/tests/Unit/ModuleTest.php` filters bindings via `str_starts_with($key, 'Marko\\Scope\\')`. After rename, the filter never matches; the test would pass vacuously without enforcing anything.
  - **Mitigation**: Task 004 rewrites the literal to `'Markommerce\\Scope\\'` and updates the human-readable test description ("does not re-bind marko/scope interfaces" → "does not re-bind markommerce/scope interfaces").
- **Risk**: phpcs/phpstan rules in markommerce are stricter than marko's and surface new violations after the rename.
  - **Mitigation**: Task 007 runs both tools; any violations in the copied code are fixed in that task before declaring the task complete. The scope source is already pretty clean per marko's own checks.
- **Risk**: adding the new packages to the root `require` block triggers `composer update` cascades on unrelated packages, polluting `composer.lock`.
  - **Mitigation**: in Task 007, run `composer update markommerce/scope markommerce/scope-pgsql --no-update` first to scope the lock update, then `composer install`.
- **Risk**: the upstream `marko/scope` `ReadmeTest` asserts the README mentions both `scope-mysql` and `scope-pgsql`. Copying the test verbatim would fail since markommerce ships only the pgsql driver.
  - **Mitigation**: Task 001 explicitly rewrites the `ReadmeTest` assertion to reference only `markommerce/scope-pgsql` and updates the README's "driver package" section to match.
- **Risk**: `Marko\Core\Exceptions\MarkoException::inferPackageName()` only recognizes `Marko\` namespaces. Missing-class errors in `Markommerce\Scope\…` classes won't produce a "composer require markommerce/scope" hint. This is a UX paper-cut, not a correctness issue — no scope/scope-pgsql code calls `inferPackageName()` directly.
  - **Mitigation**: Out of scope for this plan. Documented as a known limitation; address in a follow-up plan if/when needed.
