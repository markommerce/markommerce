# Task 007: Update scope-pgsql Integration Tests for the Scopes-Map Schema and Verify Plain SQL

**Status**: complete
**Depends on**: 002, 003, 004
**Retry count**: 0

## Description
Migrates `packages/scope-pgsql/tests/Feature/PostgresIntegrationTest.php` to the new axis schema (`scopes` map + `default` key) and adds an assertion that an all-default context produces a SQL query with no `scopes->` JSON access. This proves the headline optimization at the real-Postgres level. No `scope-pgsql` source code changes are needed — the renderer already short-circuits to a plain column when the candidate list is empty.

## Context

`scope-pgsql` has no source change in this plan. The only file that breaks under the schema change is `packages/scope-pgsql/tests/Feature/PostgresIntegrationTest.php`, which currently constructs `new ScopeAxis(...)` calls inside its fake `buildPgIntRegistry()` helper. The file does NOT use the `['hierarchy' => …]` config shape (it builds the fake registry directly from a `name => paths` array), so the only mechanical break is the `new ScopeAxis(...)` constructor signature change introduced by task 001 — the helper must pass a `default:` argument. Follow the convention established in task 001 (a second optional `array $defaults = []` parameter on the helper, defaulting to the first path in each axis).

The new test case captures the executed SQL (via `PostgresTestConnection` query logging or by reading the rendered `ScopedOrderBy` expression directly) for two contexts:

1. **All defaults** (no `ScopeContext::in()` calls, or context explicitly at declared defaults) — assert the SQL contains `ORDER BY "name"` and **no substring** `scopes->` or `COALESCE(`. Under the task 001 "first path is the default" convention, the existing `'channel' => ['default', ...]` and `'locale' => ['en', ...]` axes have defaults `'default'` and `'en'`. Either leave the context empty or call `in('channel', 'default')->in('locale', 'en')` — both must collapse to plain SQL.
2. **One axis at non-default** — set `locale` to a non-default scope (e.g. `'en.gb'`), write an override, assert the SQL contains a `COALESCE("scopes"->…)` expression. This case is already exercised by the existing override-persistence test; verify the COALESCE path still works after the renderer's short-circuit gate.

The existing `PostgresIntegrationTest` already exercises override persistence and resolution — keep those assertions and update them to the new schema where they construct axes/configs. The `new ScopeAxis(...)` call inside `buildPgIntRegistry()` must pass the third constructor argument introduced by task 001. Apply the same sentinel-default convention established by task 001: add an optional `array $defaults = []` parameter to `buildPgIntRegistry()` and, for any axis whose default is unset, prepend `'__test_default'` to the hierarchy and use it as that axis's default. This keeps the existing scope strings (`'default.web'`, `'en.gb'`, `'locale:en'`) reachable.

**Plain-SQL assertion**: the new "all defaults" test must use a fresh `ScopeContext` with no `in()` calls (or, equivalently, `in('channel', '__test_default')->in('locale', '__test_default')`). Both forms must produce empty candidate signatures and emit `ORDER BY "name"` without `scopes->` or `COALESCE(`.

`PgSqlScopedFieldRendererTest` and `ScopesGinIndexEmitterTest` operate below the registry layer (they take `ScopedFieldExpression` or entity classes directly) and do **not** construct axis configs — verify this with a quick grep and only update them if they break.

- Files to modify:
  - `packages/scope-pgsql/tests/Feature/PostgresIntegrationTest.php`
- Files to verify (touch only if broken by the ripple):
  - `packages/scope-pgsql/tests/Unit/Query/PgSqlScopedFieldRendererTest.php`
  - `packages/scope-pgsql/tests/Unit/Schema/ScopesGinIndexEmitterTest.php`
  - `packages/scope-pgsql/tests/Feature/AutoMigrationTest.php`
- Test invocation: this package's integration tests live under the `integration-destructive` Pest group and require a real Postgres instance, so run with `composer test:all` (testing.md).
- Patterns to follow:
  - Reuse `PostgresTestConnection` (`packages/scope-pgsql/tests/Feature/Helpers/PostgresTestConnection.php`).
  - Hand-rolled fakes over mocks.

## Requirements (Test Descriptions)
- [x] `it builds the postgres scope registry from the scopes-map configuration schema`
- [x] `it orders by a plain column with no scopes json access for an all-default context`
- [x] `it emits a COALESCE expression over scopes json when a non-default scope is active`
- [x] `it persists and resolves an override at a non-default scope`

## Acceptance Criteria
- All requirements have passing tests under `composer test:all`.
- `PostgresIntegrationTest.php` contains no occurrence of the string `'hierarchy'` as a config key.
- The all-default SQL assertion captures the actual executed (or rendered) SQL string and asserts both the presence of `ORDER BY` on the plain column and the absence of `scopes->` / `COALESCE(`.
- `composer test:all` is green across the entire monorepo after this task.
- Code follows code standards.

## Implementation Notes
- Fixed `buildPgIntRegistry()` to pass the required third `default:` argument to `ScopeAxis`
- Added optional `array $defaults = []` second parameter to `buildPgIntRegistry()`; any axis without a specified default uses the sentinel `'__test_default'`
- Default axis paths now include `'__test_default'` prepended (e.g. `['__test_default', 'default', 'default.web', 'default.mobile']`)
- All four new test cases added at the end of `PostgresIntegrationTest.php` in the `integration-destructive` group
- The all-default context test uses `->in('channel', '__test_default')->in('locale', '__test_default')` which produces an empty candidate list, causing the renderer to emit `'"name"'` (plain column, no COALESCE, no scopes access)
- Existing tests continue to pass unchanged since `walkUp('default.web')` still returns `['default.web', 'default']` — neither filtered by the new `__test_default` sentinel
- `PgSqlScopedFieldRendererTest`, `ScopesGinIndexEmitterTest`, and `AutoMigrationTest` required no changes
