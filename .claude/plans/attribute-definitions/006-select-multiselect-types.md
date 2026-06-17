# Task 006: select + multiselect types

**Status**: done
**Depends on**: 002, 003
**Retry count**: 0

## Description
Implement `SelectType` and `MultiselectType`. These validate values against the set of allowed
option values declared for the definition. Phase 1 validates membership only; option labels and
scoped labels are handled elsewhere/later.

## Context
- Place in `packages/attribute/src/Type/`.
- `cast(mixed $raw, AttributeDefinitionInterface $definition): mixed` — typehint the **interface**
  from task 003, NOT the entity. The signature is the SAME as every other type (no extra
  parameter for allowed options).
- **Allowed-options contract (FIXED — shared with task 011, do not deviate):** the allowed
  option values are read from `$definition->config()['options']`, which is a `list<string>` of
  the option `value` strings. The `AttributeValueValidator` (task 011) is responsible for
  populating `config()['options']` from the persisted `AttributeOption` rows BEFORE calling
  `cast()` — the type itself never queries options or touches the repository. If
  `config()['options']` is absent or empty, treat the allowed set as empty (every value is
  rejected with `InvalidAttributeOptionException`).
- `SelectType` (`code() === 'select'`, facet `Term`): accepts a single string value that is a
  member of `config()['options']`; rejects values outside it (`InvalidAttributeOptionException`).
- `MultiselectType` (`'multiselect'`, facet `Term`): accepts an array whose every element is a
  member of `config()['options']`; rejects non-arrays and unknown members; `serialize` keeps a
  JSON array.

## Requirements (Test Descriptions)
- [x] `it accepts a select value that is a member of the allowed options`
- [x] `it rejects a select value outside the allowed options`
- [x] `it accepts a multiselect array whose members are all allowed`
- [x] `it rejects a multiselect value that is not an array`
- [x] `it rejects a multiselect array containing an unknown member`
- [x] `it serializes a multiselect value as a JSON array`

## Acceptance Criteria
- Membership validation reads `$definition->config()['options']` (the FIXED contract); out-of-set
  values throw `InvalidAttributeOptionException`.
- The `cast()` signature is identical to the other types (no extra parameter).
- `facetKind()` is `Term` for both.

## Implementation Notes
- `SelectType` and `MultiselectType` placed in `packages/attribute/src/Type/`.
- Both are `readonly class` implementing `AttributeTypeInterface`.
- Options read from `$definition->config()['options']` using `array_any()` (PHP 8.5).
- `MultiselectType::serialize()` returns `json_encode($value)` (JSON array string).
- Test file `SelectMultiselectTypesTest.php` defines its own `makeOptionDefinition()` helper (inline anonymous class) to avoid depending on `makeDefinition()` from `ScalarTypesTest.php` — each file runs in its own Pest process so there is no redeclaration conflict.
