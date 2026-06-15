# Task 014: `attribute-pgsql` — schema emitters + PgSql repositories + integration tests

**Status**: pending
**Depends on**: 009, 012
**Retry count**: 0

## Description
Implement the Postgres driver: a PgSql implementation of
`AttributeDefinitionRepositoryInterface`, `module.php` binding it, and DB-backed integration
tests. Schema is provisioned from the **entities** (task 009), not hand-written `CREATE TABLE`.

## IMPORTANT — schema comes from the entities, not from emitters
The integration-test harness (`Markommerce\Testing\Schema\SchemaProvisioner`, driven by
`StoreProfile`) auto-provisions tables for **every entity under `{module}/src/Entity`** of the
modules in the test profile (`StoreProfile::resolveEntityDirs()`). Because `AttributeDefinition`
and `AttributeOption` (task 009) live in `packages/attribute/src/Entity/`, building the profile
rooted at `markommerce/attribute-pgsql` (transitively pulling in `markommerce/attribute`) will
create both tables automatically — exactly like catalog/scope tests do. **Do NOT hand-write
`CREATE TABLE` emitters as the primary mechanism.**

This mirrors `config-pgsql`, where `ConfigValueRecord` is now an entity (auto-provisioned in
tests) and the old `ConfigValuesTableEmitter` survives only as a documented *legacy/production
migration path* for things the entity attributes can't express. Two such gaps may apply here:
- **Composite `UNIQUE (entity_type, code)`**: not known to be expressible via Marko entity
  attributes (no codebase example; `#[Index]` is unused). The authoritative uniqueness guard is
  the service (task 012). If a DB constraint is also wanted, add it via a small raw
  `ALTER TABLE attribute_definitions ADD CONSTRAINT ... UNIQUE (entity_type, code)` emitter in
  this package, applied alongside provisioning — but the integration suite must still pass with
  the harness-provisioned schema, so the test that asserts a DB-level unique violation should
  apply this emitter first (or be marked clearly as exercising the emitter path).
- **FK `attribute_options.attribute_id → attribute_definitions(id) ON DELETE CASCADE`**: express
  this on the entity via `#[Column(name: 'attribute_id', references: 'attribute_definitions',
  onDelete: 'CASCADE')]` (the `references:`/`onDelete:` attribute API is confirmed in
  `CategoryTreeMarketAssignment`). The `SchemaProvisioner` emits FKs from entity metadata.

## Context
- PgSql repository extends Marko `Repository` (like `ProductRepository`), setting
  `protected const string ENTITY_CLASS = AttributeDefinition::class`; implements `findByCode`
  (via `findOneBy(['entity_type' => $entityType, 'code' => $code])`) and option access. Bind it
  in `module.php` (pattern: `packages/config-pgsql/module.php` — a `ContainerInterface` closure,
  though for a `Repository` subclass a plain class binding usually suffices; check how
  `ProductRepository` is bound in `packages/catalog/module.php`).
- JSONB read/write: the `config` column round-trips as decoded array on read / `json_encode` on
  write — confirm how the Marko entity hydrator handles a `jsonb` column (config-pgsql stores a
  pre-encoded string in `ConfigValueRecord::$value`; follow the same string-in-column approach if
  the hydrator does not auto-decode).
- Tests carry `->group('integration-destructive')` and run under `composer test:integration`
  against the self-contained Postgres (see `.claude/testing.md`). Build the profile with
  `StoreProfile::of($vendorDir, 'markommerce/attribute-pgsql', 'marko/database-pgsql')`. Use the
  `markommerce/testing` harness for isolated per-worker DBs.
- **Run the shared repository contract suite from task 012 against the real PgSql repository**
  (the same `attributeDefinitionRepositoryContract(...)` used by the in-memory fake), inside the
  `integration-destructive` group. This is the parity guard: the cheap unit fake and the real
  driver must satisfy one identical contract, so the fake can't drift. The DB-only specifics
  below (JSONB, real FK cascade, unique) are asserted in addition to the shared suite.

## Requirements (Test Descriptions)
- [ ] `it provisions the attribute_definitions and attribute_options tables from the entities`
- [ ] `it persists and reloads an attribute definition with its config jsonb`
- [ ] `it cascade-deletes options when a definition is deleted` (FK from entity metadata)
- [ ] `it finds a definition by entity type and code`
- [ ] `it round-trips select options for a definition`
- [ ] `it satisfies the attribute definition repository contract with the PgSql driver`

## Acceptance Criteria
- Integration tests pass against real Postgres under `composer test:integration`, using the
  harness-provisioned (entity-derived) schema — no hand-rolled `CREATE TABLE` required.
- The driver satisfies `AttributeDefinitionRepositoryInterface` so the service works end-to-end.
- The shared repository contract suite (task 012) passes against the PgSql driver, guaranteeing
  fake↔driver parity.
- If a DB-level composite unique is added, it is via a documented emitter, and the service guard
  remains the authoritative uniqueness check.

## Implementation Notes
(Left blank - filled in by programmer during implementation)
