# Task 004: Clean up config / config-pgsql / config-scope(-pgsql) tests

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
The config family carries a mislabeled non-DB test, source-text grep tests, "exactly one statement" count milestones, and a `Tier2EndToEnd` milestone that duplicates focused storage + boot tests. Reclassify and prune while keeping genuine JSONB/DDL/round-trip coverage.

## Context
- Files:
  - `packages/config/tests/Feature/ModulePhpTest.php` (15 `integration-destructive` tags, **no DB harness** — verified mislabeled)
  - `packages/config-pgsql/tests/Feature/PgsqlConfigStorageTest.php`, `ConfigValuesTableEmitterTest.php`
  - `packages/config-scope/tests/Feature/Tier2EndToEndTest.php`, `BootContributionTest.php`
  - `packages/config-scope-pgsql/tests/Feature/PgsqlScopedConfigStorageTest.php`, `ConfigValueOverridesTableEmitterTest.php`
- Unit equivalent: `packages/config/tests/Unit/.../InMemoryConfigStorageTest`, `InMemoryScopedConfigStorageTest`.

## Requirements (verification assertions about the resulting suite)
- [x] `it reclassifies config ModulePhpTest out of the integration group` — strip `->group('integration-destructive')` from all cases (it binds `InMemoryConfigStorage`, never connects to Postgres). Delete the 3 pure assert-on-`module.php`-array tests; relocate to `tests/Unit/` if they become pure unit (preserve `dirname(__DIR__, 2)` → package root).
- [x] `it trims PgsqlConfigStorageTest to real-Postgres cases` — keep JSONB round-trip, concurrency, version-CAS, `updated_at=NOW()`; delete the loadMany/insert/update/version-bump cases (mirror `InMemoryConfigStorageTest`) and the two `file_get_contents`-grep source-text tests.
- [x] `it trims ConfigValuesTableEmitterTest to DDL introspection` — keep JSONB/PK/version-default/timestamptz introspection; delete the "no overrides column", "no GIN index", and "exactly one statement" milestone assertions.
- [x] `it collapses config-scope Tier2EndToEndTest to one true e2e` — keep one "persists a scoped override via writer, reads back via resolver under locale=de"; delete binding-instanceof, empty-boot-closure placeholders, and storage round-trips duplicated by `PgsqlScopedConfigStorageTest`. If nothing survives beyond the kept case, keep a single focused file.
- [x] `it removes the source-grep test from config-scope BootContributionTest` — delete the `file_get_contents(module.php)->toContain('ConfigClassDiscovery')` test; keep axis-discovery + UnknownAxis throw.
- [x] `it keeps PgsqlScopedConfigStorageTest intact` — lean real ON-CONFLICT/loadOverrides/delete coverage.
- [x] `it trims ConfigValueOverridesTableEmitterTest` — keep composite-PK DDL introspection; delete the "exactly one statement" milestone.

## Acceptance Criteria
- `composer test` (excl. integration) green; `./vendor/bin/pest --group=integration-destructive packages/config-pgsql packages/config-scope-pgsql` green with DB.
- No `integration-destructive`-tagged config test runs without a DB harness.
- Each deleted storage case confirmed covered by the InMemory unit test.

## Execution (reclassification/deletion task — no Red phase)
1. Run `composer test` (excl. integration) + `./vendor/bin/pest --group=integration-destructive packages/config-pgsql packages/config-scope-pgsql` (DB) green first.
2. `ModulePhpTest` relocation: `Feature/ → Unit/` is same-depth, preserving `require dirname(__DIR__, 2) . '/module.php'`. Confirmed no pre-existing `config/tests/Unit/ModulePhpTest.php` to collide with (verified). `tests/Pest.php` is an empty stub.
3. For each deleted storage case, open the InMemory unit equivalent (`InMemoryConfigStorageTest` / `InMemoryScopedConfigStorageTest`) and confirm equivalence before deleting; keep+note otherwise.
4. After stripping `->group('integration-destructive')` from `ModulePhpTest`, confirm it no longer requires Postgres (it binds `InMemoryConfigStorage`) by running it under `composer test`.
5. Re-run both suites green; phpcs on touched files.

## Implementation Notes
- `Feature/ModulePhpTest.php` deleted; `Unit/ModulePhpTest.php` created with all 12 boot-closure tests, no `integration-destructive` tags. `dirname(__DIR__, 2)` preserved (same relative depth).
- `DeletedFilesTest.php` updated to point to `tests/Unit/ModulePhpTest.php` (was referencing deleted `tests/Feature/ModulePhpTest.php`).
- `PgsqlConfigStorageTest`: deleted 6 InMemory-mirrored cases (loadMany, insert, update, version-bump, returns-false) and 2 source-text grep tests. Kept 9 real-Postgres cases.
- `ConfigValuesTableEmitterTest`: deleted 3 milestone assertions (no overrides column, no GIN index, exactly one statement). Kept 5 DDL introspection + idempotent tests.
- `Tier2EndToEndTest`: kept single "persists a scoped override via writer.setOverride and reads it back via resolver.resolved under locale=de" test. Deleted 9 other tests (binding-instanceof, empty-boot-closure placeholders).
- `BootContributionTest`: deleted `auto-injects ConfigClassDiscovery and ScopedFieldRegistry` source-grep test; removed unused `ConfigClassDiscovery` and `ConfigRegistry` imports.
- `PgsqlScopedConfigStorageTest`: unchanged (kept intact per requirement).
- `ConfigValueOverridesTableEmitterTest`: deleted "exactly one statement" milestone test.
