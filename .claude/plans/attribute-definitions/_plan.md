# Plan: Attribute Definitions + Pluggable Type System (Custom Attributes — Phase 1)

## Created
2026-06-15

## Status
completed

## Objective
Deliver the entity-agnostic attribute kernel: runtime-defined attribute definitions, a
pluggable attribute **type** system (text/int/decimal/bool/date/select/multiselect/entityRef),
the `backing` (Column|Json) abstraction, metadata-derived reserved codes, validation, and a
Postgres driver persisting definitions + options. No attribute *values* yet (Phase 2).

## Related Issues
none

## Discovery Notes
Phase 1 of the `custom-attributes` meta-plan (`.claude/meta-plans/custom-attributes.md`).
Grounded in existing patterns:
- **Scaffolding** mirrors `packages/config` + `packages/config-pgsql` (`type: marko-module`,
  PSR-4 `Markommerce\Attribute\` / `Markommerce\Attribute\PgSql\`, `module.php` with
  `bindings`/`singletons`/`boot`).
- **Type system** models `Markommerce\Config\Casting\ValueCaster` +
  `Registry\ConfigRegistry`/`ConfigRegistryBuilder`. `AttributeTypeRegistry` is populated by
  **explicit registration in `boot`** (like `CategorySortOrderRegistry` /
  `PriceContributorRegistry`), swappable via Preferences.
- **Reserved codes** derived at runtime via Marko `EntityMetadataFactory::parse(Class)` →
  `EntityMetadata::getPropertyToColumnMap()` (no hand-maintained blocklist).
- **Exceptions** extend `Marko\Core\Exceptions\MarkoException` with named
  `message`/`context`/`suggestion` static factories.
- **Entities** extend `Marko\Database\Entity\Entity` with `#[Table]`/`#[Column]`.
- **Repositories** extend `Marko\Database\Repository\Repository` (`find`/`findOneBy`/`save`/
  `delete`/`query`); bound via `module.php`.
- **Tests**: Pest 4; unit tests use hand-written fakes / in-memory storage (no DB); DB-backed
  tests carry `->group('integration-destructive')` (see `.claude/testing.md`).

Resolved decisions (from meta-plan + clarification):
- Definitions = DB rows (merchant data); attribute **types** = code (registry).
- Ship `attribute` + `attribute-pgsql` this phase (real tables + integration tests).
- All eight types incl. `entityRef` (Phase 1 validates reference *shape* only — no resolution).
- Explicit type registration via boot.
- `backing` enum present (`Column`|`Json`); only `Json` exercised now; `Column`/Product
  wiring deferred to Phase 2 (`catalog-attribute`).
- Type-specific params (e.g. `entityRef` target entityType, decimal scale) live in a `config`
  JSONB column on the definition; select/multiselect options live in `attribute_options`.
- `code` unique per `(entityType, code)`; reserved codes scoped per `entityType`.
- decimal stored precision-safe as string; no `float` type. `type` stored as string code.
- Phase-1 validation = type-cast + `required` + select-option membership + entityRef shape;
  regex/range/min-max deferred. Reserved-code mechanism built generically, tested against a
  fixture entity; Product wiring is Phase 2.

## Scope

### In Scope
- New `attribute` package: contracts, type system, registries, entities, exceptions,
  reserved-code provider, validation, definition service + repository interface, `module.php`.
- New `attribute-pgsql` package: schema emitters + PgSql definition/option repositories +
  `module.php` binding + integration tests.
- READMEs for both packages.

### Out of Scope
- Attribute **values** on any entity (Phase 2).
- Scoped values / labels (Phase 3).
- Read model / index / faceting / search (Phases 4–6).
- `Column`-backed static attribute *wiring* to `Product` (Phase 2); only the `backing` enum
  + generic reserved-code mechanism land here.
- `entityRef` resolution (loading referenced entities) — Phase 1 validates shape only.

## Success Criteria
- [ ] Define / update / delete attribute definitions and select options (persisted in Postgres).
- [ ] Eight attribute types implemented; each casts valid values and rejects invalid loudly.
- [ ] New / overriding type registerable via Preferences (proven in a test).
- [ ] A custom `code` shadowing a native entity column is rejected (`ReservedAttributeCodeException`).
- [ ] Duplicate code per entityType and unknown type are rejected loudly.
- [ ] All tests passing (unit + integration); coverage ≥ 80%.
- [ ] Code follows project standards (phpcs, php-cs-fixer, phpstan level 8).

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Scaffold `attribute` + `attribute-pgsql` packages | - | completed |
| 002 | Attribute exceptions (MarkoException factories) | 001 | completed |
| 003 | Type contracts: `AttributeTypeInterface`, `AttributeDefinitionInterface`, `AttributeBacking`, `FacetKind` | 001 | completed |
| 004 | Scalar types: text, int, decimal | 002, 003 | completed |
| 005 | Scalar types: bool, date | 002, 003 | completed |
| 006 | select + multiselect types | 002, 003 | completed |
| 007 | entityRef type (shape validation) | 002, 003 | completed |
| 008 | `AttributeTypeRegistry` (register/get/has/all) | 002, 003 | completed |
| 009 | `AttributeDefinition` + `AttributeOption` entities | 003 | completed |
| 010 | Reserved-code provider (entity-metadata-driven) | 001 | completed |
| 011 | Attribute value validator/caster (definition + type + options) | 002, 003, 004, 005, 006, 007, 008 | completed |
| 012 | Definition service + repository interface (CRUD + guards) | 002, 008, 009, 010 | completed |
| 013 | `attribute` `module.php` (bindings, singletons, boot registers built-in types) | 004, 005, 006, 007, 008, 012 | completed |
| 014 | `attribute-pgsql` schema emitters + PgSql repositories + integration tests | 009, 012 | completed |
| 015 | READMEs for both packages | 001-014 | completed |

## Architecture Notes
- Interface/driver split: all contracts, types, registries, entities, services in `attribute`;
  Postgres specifics only in `attribute-pgsql`.
- **Definition contract vs. entity (resolves the 003↔009 forward-reference):** task 003 defines
  `AttributeDefinitionInterface` (getters: `code`, `entityType`, `type`, `backing`, `isRequired`,
  `config`). The type system (`cast`) and validator build against this interface — exactly as
  `config`'s `ValueCaster` casts against the `ConfigDefinition` value object, not the DB row. The
  `AttributeDefinition` entity (task 009) *implements* it. Dependency flows one way: 009 → 003;
  no circular/forward reference, and tasks 004–007/011 compile before the entity exists.
- `AttributeTypeInterface`: `code(): string`, `cast(mixed $raw, AttributeDefinitionInterface
  $def): mixed` (throws `InvalidAttributeValueException`), `serialize`/`deserialize` for JSON,
  `facetKind(): FacetKind` (stub for Phases 5–6). Types are stateless `readonly` classes and have
  ONE uniform `cast` signature — no per-type extra parameters.
- **Select/multiselect allowed-options contract (FIXED, shared 006↔011):** select/multiselect
  types read the allowed value set from `$definition->config()['options']` (`list<string>`). The
  `AttributeValueValidator` (011) populates this (from caller-supplied options) via a read-only
  definition view before delegating to `cast`; types never query the option repository.
- `AttributeDefinition` entity columns: `id`, `code`, `entity_type`, `type`, `label`,
  `required` (bool), `default_value` (nullable), `backing` (enum, default `Json`), flags
  `filterable`/`searchable`/`facetable`/`scopable`, `config` (JSONB, nullable, type params).
  Implements `AttributeDefinitionInterface`.
- `AttributeOption` entity: `id`, `attribute_id` (FK → definition, `onDelete: CASCADE`), `value`,
  `label`, `position`.
- **Schema provisioning:** `AttributeDefinition`/`AttributeOption` are Marko entities, so the
  `markommerce/testing` `SchemaProvisioner` auto-creates their tables in integration tests (it
  scans `{module}/src/Entity` of profile modules — same as catalog/scope/the entity-backed
  `ConfigValueRecord`). Task 014 relies on this, NOT on hand-written `CREATE TABLE` emitters.
  The composite `UNIQUE (entity_type, code)` is enforced authoritatively in the service (012);
  a DB-level constraint is added in 014 only if/when Marko's attribute API supports it (else via
  a documented raw `ALTER TABLE` emitter).
- Reserved codes: `ReservedCodeProvider` wraps `EntityMetadataFactory`; given an entity class
  returns its declared column/property names. The exact `EntityMetadata` accessor is confirmed at
  implementation time against the Docker-cloned Marko source (assumed `getPropertyToColumnMap()`
  / `columns`); a missing accessor must fail loudly, never yield an empty reserved set. The
  definition service rejects collisions. Built generically here; `Product` binding is Phase 2.
- No `final`; `declare(strict_types=1)` everywhere; constructor injection; `readonly class`
  where immutable; explicit `@throws`; PHP 8.5 `array_*` idioms.

## Risks & Mitigations
- **Marko `EntityMetadataFactory` API drift** (reserved codes depend on it): pin to the
  `getPropertyToColumnMap()` / `columns` API confirmed in discovery; cover with a fixture-entity
  unit test so a drift fails loudly.
- **entityRef over-scoping**: explicitly limit Phase 1 to reference *shape* validation (no
  loading); resolution is a later phase. Stated in task 007.
- **decimal precision**: store/cast as string, never float; assert precision is preserved.
- **Definition↔option integrity** only enforceable in the pgsql driver (FK); the in-memory
  fake mimics it for unit tests — note divergence in tasks 012 and 014. **Mitigation: a shared
  repository contract suite** (authored in 012, run against the fake in the unit tier and against
  the PgSql driver in the integration tier) guarantees both implementations satisfy one identical
  contract, so the fake cannot drift from the real driver.
- **Composite `UNIQUE (entity_type, code)` may not be expressible via Marko entity attributes**
  (no codebase example; `#[Index]` unused). Mitigation: the service (012) is the authoritative
  uniqueness guard; the DB constraint is optional/emitter-backed (014). Do not block on it.
- **Registry override semantics**: `AttributeTypeRegistry::register()` must OVERRIDE on duplicate
  `code()` — the cited `CategorySortOrderRegistry` does the opposite (silently ignores). Task 008
  flags this so a worker doesn't copy the wrong behavior and break the Preference-swap test (013).
- **Schema-source ambiguity**: task 014 must use entity-backed provisioning (harness scans
  `src/Entity`), not the config-pgsql-style raw `CREATE TABLE` emitter. Flagged in 014.
