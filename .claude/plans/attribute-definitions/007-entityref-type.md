# Task 007: entityRef type (shape validation only)

**Status**: complete
**Depends on**: 002, 003
**Retry count**: 0

## Description
Implement `EntityRefType` — an attribute whose value references another entity by identifier.
Phase 1 validates the **reference shape only** (a valid identifier and a declared target entity
type); it does NOT load or verify the referenced entity exists (that is a later phase).

## Context
- Place in `packages/attribute/src/Type/`.
- `cast(mixed $raw, AttributeDefinitionInterface $definition): mixed` — typehint the **interface**
  from task 003, NOT the entity.
- `code() === 'entityRef'`, `facetKind()` is `Term`.
- The target entity type is declared per-definition in `$definition->config()['targetEntityType']`
  (the type-specific params JSONB column). If absent, casting must fail loudly with a clear
  suggestion to set it.
- Accepts a positive integer identifier (matching the auto-increment PK convention seen on
  `Product`); rejects non-integers, zero, and negatives.
- `serialize`/`deserialize` keep the identifier as an int in JSON.
- Explicitly out of scope: loading the referenced entity, FK enforcement, cross-entity
  existence checks. Note this in the class docblock.

## Requirements (Test Descriptions)
- [x] `it accepts a positive integer reference when a target entity type is configured`
- [x] `it rejects a non-integer reference value`
- [x] `it rejects a zero or negative reference value`
- [x] `it fails loudly when no target entity type is configured`

## Acceptance Criteria
- Shape validation only; no entity loading.
- Missing `targetEntityType` throws `InvalidAttributeValueException` with an actionable suggestion.

## Implementation Notes
- `EntityRefType` implemented as a `readonly class` in `packages/attribute/src/Type/EntityRefType.php`.
- Missing `targetEntityType` in config throws `InvalidAttributeValueException` directly (not via the `forValue` factory, since the raw value itself isn't the problem — the config is).
- Non-integer and non-positive integers throw via `InvalidAttributeValueException::forValue()`.
- `serialize` returns the int as-is; `deserialize` casts to int to handle JSON-decoded numeric strings.
- `facetKind()` returns `FacetKind::Term`; `code()` returns `'entityRef'`.
