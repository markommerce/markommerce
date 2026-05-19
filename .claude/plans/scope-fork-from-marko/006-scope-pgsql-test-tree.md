# Task 006: Copy & Rename markommerce/scope-pgsql Test Tree

**Status**: complete
**Depends on**: 003, 005
**Retry count**: 0

## Description
Copy every test file from `marko/packages/scope-pgsql/tests/` (except the scaffolding/sentinel tests created in Task 004) into `markommerce/packages/scope-pgsql/tests/`, rewriting all `Marko\Scope\PgSql\` → `Markommerce\Scope\PgSql\` and `Marko\Scope\` (parent) → `Markommerce\Scope\` references. Marko\Database\…, Marko\Core\… imports are preserved.

## Context

- Source test files to copy (under `/home/michal/www/marko/marko/packages/scope-pgsql/tests/`):
  - `Unit/Query/PgSqlScopeSortRendererTest.php`
  - `Feature/AutoMigrationTest.php`
- Note: upstream `marko/scope-pgsql/tests/` has NO `Pest.php` file. Do not invent one — Pest discovers tests without it.
- Do NOT re-copy: `PackageScaffoldingTest.php`, `Unit/ReadmeTest.php`, `Unit/ModuleTest.php` — those were authored in Task 004.
- Rename rules identical to Task 005.
- **`Feature/AutoMigrationTest.php` is purely in-memory** — verified by reading the source file. It instantiates `EntityMetadataFactory`, `SchemaRegistry`, `DiffCalculator`, and `PgSqlGenerator` and asserts on the generated SQL strings. No `ConnectionInterface` is constructed, no PDO is opened, no `composer test:all` group tag is required. The test MUST run under the default `composer test` (no `->group('integration-destructive')` tag). Tagging it as destructive would silently exclude legitimate coverage.

## Requirements (Test Descriptions)

- [x] `it copies every Unit test file from marko/scope-pgsql/tests/Unit/ into packages/scope-pgsql/tests/Unit/`
- [x] `it copies every Feature test file from marko/scope-pgsql/tests/Feature/ into packages/scope-pgsql/tests/Feature/`
- [x] `it has no remaining Marko\\Scope\\ references in packages/scope-pgsql/tests/` (both single- and double-backslash forms)
- [x] `it passes the full Unit and Feature test suites under composer test` (AutoMigrationTest is in-memory; no destructive group required)
- [x] `the AutoMigrationTest carries no ->group('integration-destructive') tag` (assert by reading the file for the absence of the tag, since adding one would silently exclude the test)

## Acceptance Criteria

- `./vendor/bin/pest packages/scope-pgsql/tests` exits 0 under `composer test` (all unit + feature tests pass without any group flags).
- Every test file uses `Markommerce\Scope\…` namespaces in its `namespace`/`use` lines.
- `AutoMigrationTest` runs in the default suite (no `->group('integration-destructive')` tag added).
- Scaffolding tests from Task 004 keep passing.

## Implementation Notes

- Created `packages/scope-pgsql/tests/Unit/Query/PgSqlScopeSortRendererTest.php` — copied from upstream with `Marko\Scope\PgSql\` and `Marko\Scope\` references renamed to `Markommerce\Scope\PgSql\` and `Markommerce\Scope\` respectively. No namespace declaration (pure Pest file). `Marko\Database\` imports preserved.
- Created `packages/scope-pgsql/tests/Feature/AutoMigrationTest.php` — copied from upstream with `namespace Marko\Scope\PgSql\Tests\Feature` renamed to `Markommerce\Scope\PgSql\Tests\Feature`, `Marko\Scope\Storage\HasScopes` and `Marko\Scope\Storage\HasScopesInterface` renamed to `Markommerce\Scope\Storage\…`. All `Marko\Database\…` imports preserved. No `->group('integration-destructive')` tag.
- Created `packages/scope-pgsql/tests/Unit/CopiedTestTreeTest.php` as the sentinel/structural test file that verifies requirements 1, 2, 3, and 5.
- All 36 tests in `packages/scope-pgsql/tests/` pass under the default `composer test` run.
