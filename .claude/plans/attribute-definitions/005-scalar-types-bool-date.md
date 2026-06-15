# Task 005: Scalar types — bool, date

**Status**: pending
**Depends on**: 002, 003
**Retry count**: 0

## Description
Implement the `BoolType` and `DateType` attribute types as stateless `readonly` classes
implementing `AttributeTypeInterface`, casting/validating and throwing
`InvalidAttributeValueException` on mismatch.

## Context
- Place in `packages/attribute/src/Type/`.
- `cast(mixed $raw, AttributeDefinitionInterface $definition): mixed` — typehint the **interface**
  from task 003, NOT the entity.
- `BoolType` (`code() === 'bool'`, facet `Term`): accepts a strict `bool`; rejects truthy/falsy
  strings or ints (strict, matching `ValueCaster`'s `bool` branch).
- `DateType` (`'date'`, facet `Range`): accepts an ISO-8601 date string (or `DateTimeImmutable`)
  and normalizes to a canonical ISO string; rejects unparseable values. `serialize` produces the
  ISO string; `deserialize` returns it (no live `DateTimeImmutable` required for Phase 1, but the
  normalized string must be stable).

## Requirements (Test Descriptions)
- [ ] `it casts a true boolean through the bool type`
- [ ] `it rejects the string true in the bool type`
- [ ] `it casts a valid ISO date string through the date type`
- [ ] `it normalizes an accepted date to a canonical ISO string`
- [ ] `it rejects an unparseable date value`

## Acceptance Criteria
- Each type reports the correct `code()` and `facetKind()`.
- Invalid values throw `InvalidAttributeValueException`.
- Date normalization is deterministic and JSON-safe.

## Implementation Notes
(Left blank - filled in by programmer during implementation)
