# Task 003: Type contracts — `AttributeTypeInterface`, `AttributeDefinitionInterface`, `AttributeBacking`, `FacetKind`

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Define the pure contracts the type system is built on: the `AttributeTypeInterface` every
attribute type implements, the `AttributeDefinitionInterface` it casts against, the
`AttributeBacking` enum (`Column`|`Json`), and the `FacetKind` enum (`Term`|`Range`|`None`)
that types declare for later faceting phases.

## IMPORTANT — break the 003↔009 forward-reference with an interface
The type system (`cast()`, tasks 004–007) must NOT depend on the persistence *entity*
`AttributeDefinition` (task 009). Mirroring `config` — where `ValueCaster::cast()` takes the
`ConfigDefinition` **value object**, not the DB row — `cast()` here takes
`AttributeDefinitionInterface`. The entity (task 009) *implements* this interface. This makes
the dependency direction one-way (009 → 003), removes the circular/forward reference, and lets
tasks 004–007 and 011 compile against a stable contract before the entity exists.

## Context
- Place in `packages/attribute/src/Contracts/` (interfaces) and `packages/attribute/src/Type/`
  (enums) — match how `config` groups `Contracts/` vs value objects.
- `AttributeDefinitionInterface` (in `Contracts/`) — the read contract the type system casts
  against. Methods (getters only, no persistence): `code(): string`, `entityType(): string`,
  `type(): string` (the type code), `backing(): AttributeBacking`, `isRequired(): bool`,
  `config(): array` (type-specific params JSONB, e.g. `scale`, `targetEntityType`; default
  `[]`). The entity in task 009 implements this. Do NOT reference the entity here.
- `AttributeTypeInterface` methods:
  - `code(): string` — the string type code stored on definitions (e.g. `text`, `select`).
  - `cast(mixed $raw, AttributeDefinitionInterface $definition): mixed` — validate + coerce a
    raw value; `@throws InvalidAttributeValueException`. Takes the **interface**, never the
    entity (see "IMPORTANT" above).
  - `serialize(mixed $value): mixed` / `deserialize(mixed $stored): mixed` — JSON-safe round trip.
  - `facetKind(): FacetKind` — declares how this type facets (stub usage until Phase 5).
- `AttributeBacking` enum: cases `Column`, `Json`. Default for new definitions is `Json`.
- `FacetKind` enum: cases `Term`, `Range`, `None`.
- These are contracts/enums only — no concrete type logic here.
- NOTE on select/multiselect allowed options: `cast()` does NOT receive allowed option values
  via its signature. The select/multiselect types (task 006) read allowed values from
  `$definition->config()['options']` (a `list<string>` the validator populates before calling
  `cast`). This keeps `AttributeTypeInterface::cast` uniform across all eight types. See task 006
  and 011 for the shared contract.

## Requirements (Test Descriptions)
- [x] `it declares code cast serialize deserialize and facetKind on AttributeTypeInterface`
- [x] `it declares the definition getters on AttributeDefinitionInterface`
- [x] `it types cast against AttributeDefinitionInterface not the entity`
- [x] `it exposes Column and Json cases on the AttributeBacking enum`
- [x] `it exposes Term Range and None cases on the FacetKind enum`
- [x] `it allows a test double implementing AttributeTypeInterface to be instantiated`

## Acceptance Criteria
- Interface and enums are stable and importable by tasks 004-009 and 011.
- `AttributeTypeInterface::cast` typehints `AttributeDefinitionInterface`, not the entity.
- No concrete type behavior leaks into this task.

## Implementation Notes
- Created `packages/attribute/src/Type/AttributeBacking.php` — pure enum with `Column` and `Json` cases.
- Created `packages/attribute/src/Type/FacetKind.php` — pure enum with `Term`, `Range`, and `None` cases.
- Created `packages/attribute/src/Contracts/AttributeDefinitionInterface.php` — read contract with getters: `code()`, `entityType()`, `type()`, `backing()`, `isRequired()`, `config()`.
- Created `packages/attribute/src/Contracts/AttributeTypeInterface.php` — type contract with `code()`, `cast()` (taking `AttributeDefinitionInterface`), `serialize()`, `deserialize()`, `facetKind()`.
- `cast()` references `InvalidAttributeValueException` via `@throws` (already exists from task 002).
- All 6 requirements verified via reflection tests in `tests/Unit/Type/TypeContractsTest.php`.
