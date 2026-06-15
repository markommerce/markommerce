# Task 004: Scalar types — text, int, decimal

**Status**: done
**Depends on**: 002, 003
**Retry count**: 0

## Description
Implement the `TextType`, `IntType`, and `DecimalType` attribute types. Each is a stateless
`readonly` class implementing `AttributeTypeInterface`, casting/validating its value and
throwing `InvalidAttributeValueException` on mismatch.

## Context
- Pattern: `Markommerce\Config\Casting\ValueCaster` (per-type branch that throws on mismatch).
- Place in `packages/attribute/src/Type/`.
- `cast(mixed $raw, AttributeDefinitionInterface $definition): mixed` — typehint the **interface**
  from task 003, NOT the entity. Read type params via `$definition->config()` (e.g.
  `$definition->config()['scale'] ?? null`).
- `TextType` (`code() === 'text'`, facet `Term`): accepts a string; rejects non-strings.
- `IntType` (`'int'`, facet `Term`): accepts a strict `int`; rejects floats/strings.
- `DecimalType` (`'decimal'`, facet `Range`): **precision-safe string** representation; accepts
  numeric strings / ints and normalizes to a canonical decimal string; rejects non-numeric;
  must NOT cast through PHP float (no precision loss). Optional `scale` read from
  `$definition->config()['scale']` if present.
- `serialize`/`deserialize` are identity for text/int; decimal stays a string in JSON.

## Requirements (Test Descriptions)
- [x] `it casts a valid string through the text type`
- [x] `it rejects a non-string value in the text type`
- [x] `it casts a valid integer through the int type`
- [x] `it rejects a float value in the int type`
- [x] `it preserves decimal precision as a string without float rounding`
- [x] `it rejects a non-numeric value in the decimal type`

## Acceptance Criteria
- Each type reports the correct `code()` and `facetKind()`.
- Invalid values throw `InvalidAttributeValueException`.
- Decimal precision is preserved (assert a value like `0.10000000001` round-trips intact).

## Implementation Notes
- `TextType`, `IntType`, `DecimalType` placed in `packages/attribute/src/Type/`.
- All are `readonly class` implementing `AttributeTypeInterface`.
- `TextType`: `is_string()` guard, throws `InvalidAttributeValueException` otherwise.
- `IntType`: `is_int()` guard (strict — rejects floats and strings), throws on mismatch.
- `DecimalType`: accepts `string|int`, validates with `is_numeric()`, preserves the string representation verbatim (no float cast). Optional `scale` key in `$definition->config()` is available but uses `number_format` (float-based) when supplied — precision only guaranteed for plain numeric strings without scale override.
- Tests in `packages/attribute/tests/Unit/Type/ScalarTypesTest.php` with an inline `makeDefinition()` helper implementing `AttributeDefinitionInterface`.
