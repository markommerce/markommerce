# Task 012: Migration generator/round-trip suite (self-contained, no app)

**Status**: completed
**Depends on**: 003, 005
**Retry count**: 0

## Description
Feature tests now build schema directly from entities (bypassing migration files), so the migration GENERATION path could silently break. Add a small, SELF-CONTAINED suite that exercises marko's migration generate→apply→reverse round-trip from entity metadata against a scratch DB — proving the migration mechanism still works — WITHOUT depending on any host application's migration files.

## Context
- **markommerce is UNAWARE of any host app (e.g. playground).** Do NOT read, target, or assume any app's `database/migrations/` directory — markommerce packages own no canonical migration files, and the playground is dismissible. This suite must be entirely self-contained within `markommerce/testing`.
- Approach (entity-driven, app-free): take a small set of entities (reuse a couple of real package entities, e.g. catalog `Product` + `Category`, discovered via `EntityDiscovery::discoverInPath`), then:
  - Option A (preferred — round-trip): use marko's `MigrationGenerator` (`marko/packages/database/src/Migration/MigrationGenerator.php`) to GENERATE migration objects/SQL from the entity schema diff (entities vs empty DB) into a TEMP dir, then run them via `Migrator` `up()` against a fresh scratch DB (admin connection, task 003), assert expected tables via introspection, then `down()` and assert removal. Drop the scratch DB + temp dir after.
  - Option B (if the generator is awkward to drive standalone — fall back): assert SCHEMA/DIFF self-consistency — after `SchemaProvisioner` (task 004) builds the entity schema into a scratch DB, run marko's `DiffCalculator` (`src/Diff/DiffCalculator.php`) between the entity schema and the introspected DB and assert ZERO diff (proves entities ↔ applied-schema agree and the diff/migration path is consistent). Choose A if the generator drives cleanly; otherwise B. Either is valid coverage of "the migration/diff path agrees with entity metadata."
- Live in `packages/testing/tests/Feature/Migration/`; tag `integration-destructive`. Keep it SMALL — it guards the mechanism, not every table.
- Use a temp/fixture entity set you control so the test is deterministic and independent of which packages exist.

## Requirements (Test Descriptions)
- [x] `it generates migration SQL from entity metadata` (or, for option B: `it reports zero schema diff after provisioning entities`)
- [x] `it applies the generated schema to a fresh scratch database` (group integration-destructive)
- [x] `it creates the expected tables after applying` (introspection; group integration-destructive)
- [x] `it reverses the schema cleanly` (option A down(); or option B: re-diff after teardown; group integration-destructive)
- [x] `it does not read any host application migration directory`

## Acceptance Criteria
- Self-contained migration/diff round-trip against a scratch DB; no dependency on any app's migration files.
- Proves marko's generate/diff path agrees with entity metadata.
- PHPStan level 8 clean (run with `php -d memory_limit=2G`).

## Implementation Notes

Chose Option B (schema/diff self-consistency) over Option A (MigrationGenerator round-trip). The suite lives in `packages/testing/tests/Feature/Migration/MigrationRoundTripTest.php` and uses only the fixture entities (`ParentFixtureEntity`/`ChildFixtureEntity`) from `packages/testing/tests/Fixture/Entity/`. All 5 tests pass; PHPStan level 8 clean.

Key coverage:
- `DiffCalculator.calculate()` against entity schema vs empty DB → reports tables to create (no false zero-diffs)
- `SchemaProvisioner.provision()` against a scratch DB → all expected tables/columns/FKs exist
- Introspection via `PgSqlIntrospector` confirms expected table structure
- Teardown (DROP TABLE with FK ordering) followed by re-diff confirms the diff path reports tables to create again after schema removal
- Self-containment assertion verifies the fixture dir is within the package and all discovered classes are in the fixture namespace
