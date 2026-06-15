# Task 010: Reserved-code provider (entity-metadata-driven)

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Implement `ReservedCodeProvider`, a generic service that, given an entity class, returns the set
of attribute codes that are reserved because they collide with the entity's declared columns.
This is the mechanism that prevents a custom attribute from shadowing a native column. It is
built generically here and tested against a fixture entity; the `Product` binding lands in
Phase 2.

## Context
- Marko API: `Marko\Database\Entity\EntityMetadataFactory::parse($entityClass)` → `EntityMetadata`
  is confirmed in this repo (`SchemaProvisioner`, `ScopedEntityValidator`). The exact accessor
  for column/property names is **NOT verifiable locally** (Marko is the Docker-cloned framework),
  so treat the precise method name as a discovery step during implementation, not a given:
  - The plan assumes `getPropertyToColumnMap()` (property→column map) and/or a public
    `$metadata->columns` of `ColumnMetadata` (with a `->name`). `$metadata->extenders` is a
    confirmed public property, so direct property access on `EntityMetadata` is plausible.
  - At implementation time, inspect the actual `EntityMetadata` API (in the Docker container:
    the cloned `marko/database` source) and use whatever returns the declared columns +
    properties. Reserve **both** property names and column names to be safe.
  - If the assumed accessor is absent, FAIL LOUDLY in the fixture test (do not silently fall back
    to an empty set — an empty reserved set would let collisions through). The `_plan.md` Risks
    section tracks this as the key API-drift risk.
- Place in `packages/attribute/src/Reserved/ReservedCodeProvider.php`.
- API: `reservedCodes(string $entityClass): array<string>` returning a de-duplicated list.
  Inject `EntityMetadataFactory` via the constructor.
- Create a small fixture entity in the test suite (an `Entity` subclass with a couple of
  `#[Column]` properties) to assert derivation without depending on `Product`.

## Requirements (Test Descriptions)
- [x] `it derives reserved codes from a fixture entity column names`
- [x] `it includes property names as reserved codes`
- [x] `it returns a de-duplicated list of reserved codes`
- [x] `it reflects added columns when the fixture entity declares more`

## Acceptance Criteria
- No hard-coded column list — derivation is purely from entity metadata.
- Works for an arbitrary entity class (generic), proven via the fixture.

## Implementation Notes
- Discovered `EntityMetadata` API: `$metadata->columns` (array of `ColumnMetadata`, each with `->name`) and `$metadata->properties` (array keyed by property name). Both are public readonly properties.
- `ReservedCodeProvider::reservedCodes()` merges column names and property names, deduplicates via `array_unique`, re-indexes via `array_values`.
- Two fixture entities created: `SimpleEntity` (3 columns, with `productSku`/`product_sku` asymmetry) and `ExtendedEntity` (5 columns) for the "reflects added columns" test.
- Requirements 3 and 4 passed immediately because the generic implementation already handled them — noted as over-implementation from earlier steps.
