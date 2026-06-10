# Task 005: Clean up scope / scope-pgsql tests

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
`scope-pgsql/PostgresIntegrationTest` (801 lines) mixes genuine Postgres coverage with many renderer/enumerator/registry unit-dups and two near-identical override round-trips. Several `scope/` Feature tests use a fake `ConnectionInterface` (no real DB) and are mislabeled. Prune to real DDL/JSONB/CAS coverage and relocate the pure unit tests.

## Context
- Files:
  - `packages/scope-pgsql/tests/Feature/PostgresIntegrationTest.php` (801 ln)
  - `packages/scope-pgsql/tests/Feature/AutoMigrationTest.php` (no DB conn, no group — pure `DiffCalculator`/`PgSqlGenerator` logic)
  - `packages/scope/tests/Feature/ScopedOverridesPersistenceTest.php` + `ScopedOverridesEntityDirtyTrackingTest.php` (fake connection, no group)
  - `packages/scope/tests/Feature/DefaultScopeResolutionTest.php` (in-memory fixtures, no group)
  - `packages/scope/tests/Feature/BridgeContributionTest.php` (no DB; DependencyResolver boot wiring)
- Unit equivalents already covering renderer/enumerator/resolver: `PgSqlScopedFieldRendererTest` (16 cases), `SignatureCandidateEnumeratorTest`, `ScopeResolverTest`, `ScopedOrderByTest`.

## Relocation targets (verified safe — same-depth `Feature/ → Unit/`, no destination collisions)
All relocated files are top-level `tests/Feature/*.php` (depth 2); moving to `tests/Unit/*.php` preserves every `dirname(__DIR__, 2)` package-root reference (incl. `DefaultScopeResolutionTest`'s `require dirname(__DIR__, 2) . '/config/scope.php'`). Confirmed no pre-existing `tests/Unit/AutoMigrationTest.php` or `tests/Unit/DefaultScopeResolutionTest.php` to collide with. `tests/Pest.php` in both packages is an empty stub (no `uses()` to update).

## Requirements (verification assertions about the resulting suite)
- [ ] `it trims PostgresIntegrationTest from ~13 to ~4` — keep JSONB round-trip, GIN-index introspection, transaction-rollback, column-type check; delete the COALESCE-string / candidate-signature-list / enumerator-cap-warning / registry-construction / "skip when DB_HOST unset" cases (all covered by named unit tests); merge the two byte-identical override round-trips into one.
- [ ] `it relocates AutoMigrationTest to Unit` — no DB connection and no group; move `Feature/ → Unit/` (recompute `dirname` if nesting changes) since it is pure schema-diff logic.
- [ ] `it merges and relocates the scope override fake-connection tests` — `ScopedOverridesEntityDirtyTrackingTest`'s single case is a subset of `ScopedOverridesPersistenceTest`'s dirty-tracking case; merge into one file and move `Feature/ → Unit/` (both use a fake `ConnectionInterface`, not Postgres).
- [ ] `it relocates DefaultScopeResolutionTest to Unit` — in-memory fixtures + recording fake builder; behavior already in `ScopeResolverTest` + `ScopedOrderByTest`. Move; delete any case that is a strict duplicate of those.
- [ ] `it leaves BridgeContributionTest untagged in Feature` — it boots a container (no-DB integration of wiring); ensure it carries no `integration-destructive` tag; optionally trim to the one DependencyResolver boot-ordering + `container->call` injection case if the rest duplicate it. **Do not delete any case that is the last user of `tests/Support/MixedEntity.php` or `PlainEntity.php` without also deleting those fixtures** — these two Support classes are referenced ONLY by this file (the `ScopedOverrides*` tests use inline anonymous `ConnectionInterface` classes, not Support fixtures), so over-trimming here orphans them. If trimming removes the last reference, delete the fixtures too.
- [ ] `it keeps every surviving DB case tagged and skip-guarded`.

## Acceptance Criteria
- `./vendor/bin/pest --group=integration-destructive packages/scope-pgsql` green with DB; relocated files run under `composer test`.
- Each deleted renderer/enumerator/registry case confirmed covered by the named unit test.
- No dangling fixture/helper references after moves.

## Execution (deletion/relocation task — no Red phase)
1. Run `./vendor/bin/pest packages/scope packages/scope-pgsql` (DB available) green first.
2. For each deleted `PostgresIntegrationTest` case, open the named unit (`PgSqlScopedFieldRendererTest`, `SignatureCandidateEnumeratorTest`, `ScopeResolverTest`, `ScopedOrderByTest`) and confirm equivalence before deleting; keep+note otherwise.
3. Relocate files (git mv `Feature/X.php` → `Unit/X.php`); do not change `dirname` depth (same-depth move).
4. Re-run both suites green (with and without DB); phpcs on touched files.

## Implementation Notes
(Left blank — filled in during implementation.)
