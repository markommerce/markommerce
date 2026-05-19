# Task 004: Bootstrap markommerce/scope-pgsql Package Skeleton

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Create the `markommerce/scope-pgsql` package directory with composer.json, LICENSE, .gitattributes, README.md, module.php, and the scaffolding/sentinel tests (`PackageScaffoldingTest`, `ModuleTest`, `ReadmeTest`). All names, namespaces, and dependency strings are rewritten for Markommerce. The composer.json depends on `markommerce/scope` at `self.version` (not `marko/scope`).

## Context

- Source files to diff against (under `/home/michal/www/marko/marko/packages/scope-pgsql/`):
  - `composer.json`, `module.php`, `README.md`, `LICENSE`, `.gitattributes`
  - `tests/PackageScaffoldingTest.php`, `tests/Unit/ModuleTest.php`, `tests/Unit/ReadmeTest.php`
- Target paths under `packages/scope-pgsql/`.
- composer.json `require`: `php: ^8.5`, `marko/core: self.version`, `markommerce/scope: self.version`, `marko/database-pgsql: self.version`. `require-dev`: `pestphp/pest: ^4.0`. PSR-4: `Markommerce\\Scope\\PgSql\\` → `src/`. PSR-4 dev: `Markommerce\\Scope\\PgSql\\Tests\\` → `tests/`. `extra.marko.module: true`.
- module.php binds `Markommerce\Scope\Query\ScopeSortRendererInterface` → `Markommerce\Scope\PgSql\Query\PgSqlScopeSortRenderer` (the FQCNs don't need to be autoloadable for the scaffolding tests, since `ModuleTest` only reads the module.php as an array).
- `PackageScaffoldingTest` should assert: name `markommerce/scope-pgsql`, requires `markommerce/scope` (NOT `marko/scope`), requires `marko/database-pgsql`, PSR-4 prefix `Markommerce\\Scope\\PgSql\\`, PSR-4 test prefix `Markommerce\\Scope\\PgSql\\Tests\\`, has `extra.marko.module = true`, has no version field, has a `module.php` returning an array with `bindings`.
- `ReadmeTest` adapts the upstream `marko/scope-pgsql` ReadmeTest assertions for the new package: title `# markommerce/scope-pgsql`, install command `composer require markommerce/scope-pgsql`, mentions that `markommerce/scope` is installed as a transitive dep, has a quick example, has a Documentation section.
- README.md: title `# markommerce/scope-pgsql`, brief description (PostgreSQL driver for `markommerce/scope` — jsonb + scoped ORDER BY), Installation section, Quick Example using `Markommerce\Scope\…` namespaces, Documentation link.
- **`ModuleTest` rewrites required** (the upstream test at `marko/scope-pgsql/tests/Unit/ModuleTest.php` has TWO Markommerce-specific gotchas):
  1. The test "does not re-bind marko/scope interfaces" uses `str_starts_with($key, 'Marko\\Scope\\')` to filter bindings. After rename, the literal `'Marko\\Scope\\'` no longer matches any binding key (they all start with `Markommerce\\Scope\\`), so the test would pass vacuously. Rewrite the literal to `'Markommerce\\Scope\\'`. Also rewrite the test description from "does not re-bind marko/scope interfaces" to "does not re-bind markommerce/scope interfaces".
  2. The test "binds ScopeSortRendererInterface to PgSqlScopeSortRenderer" imports `Marko\Scope\Query\ScopeSortRendererInterface` and `Marko\Scope\PgSql\Query\PgSqlScopeSortRenderer` — rewrite both to `Markommerce\…` per the standard rename.

## Requirements (Test Descriptions)

- [ ] `it has a valid composer.json with name markommerce/scope-pgsql and extra.marko.module true`
- [ ] `it requires markommerce/scope and marko/database-pgsql in composer.json`
- [ ] `it has no version field in composer.json`
- [ ] `it autoloads PSR-4 namespace Markommerce\Scope\PgSql\ from packages/scope-pgsql/src/` (asserts the JSON-encoded key `Markommerce\\Scope\\PgSql\\`)
- [ ] `it autoloads tests namespace Markommerce\Scope\PgSql\Tests\ from packages/scope-pgsql/tests/` (asserts the JSON-encoded key `Markommerce\\Scope\\PgSql\\Tests\\`)
- [ ] `it has a module.php returning an array with a bindings key`
- [ ] `it has a README.md with title # markommerce/scope-pgsql and a markommerce/scope-pgsql install command`
- [ ] `it does not re-bind markommerce/scope interfaces` (filter uses the literal `'Markommerce\\Scope\\'`; description updated from upstream's `marko/scope`)

## Acceptance Criteria

- All scaffolding/sentinel tests pass: `./vendor/bin/pest packages/scope-pgsql/tests/PackageScaffoldingTest.php tests/Unit/ModuleTest.php tests/Unit/ReadmeTest.php`.
- composer.json's `require` block does NOT mention `marko/scope` (it must be `markommerce/scope`).
- README references only `markommerce/scope-pgsql` and `markommerce/scope`, never `marko/scope*`.
- `.gitattributes` mirrors marko/scope-pgsql's export-ignore list.
- `declare(strict_types=1);` is present in every PHP file authored here, no `final` classes.

## Implementation Notes
(Left blank — filled in during implementation)
