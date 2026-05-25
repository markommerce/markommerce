# Task 007: `ValueCaster`

**Status**: completed
**Depends on**: 002, 004
**Retry count**: 0

## Description
Implement strict type casting from a JSON-decoded scalar/array (as it comes out of `ConfigStorage`) to the declared PHP type recorded in `ConfigDefinition`. On any mismatch — wrong scalar type, unparseable enum value, malformed array shape — throw `InvalidConfigValueException` with the offending key, raw value, and target type. No silent coercion.

## Context
- Supported declared types in v1: `string`, `int`, `float`, `bool`, `array` (untyped), and backed enum classes (`IntBackedEnum` / `StringBackedEnum` instances)
- Out of scope in v1: object value types (no `DateTimeImmutable`, no nested DTOs) — those throw `InvalidConfigClassException` at registry-build time (task 005)
- Caster is invoked at the boundary: read path = decode JSON → cast → return; write path = type check (against definition) → encode JSON → store
- For enums: detect `is_subclass_of($type, BackedEnum::class)` and use `::tryFrom()`; null result throws

## Requirements (Test Descriptions)
- [x] `it returns a string unchanged when target type is string`
- [x] `it returns an int unchanged when target type is int`
- [x] `it returns a float unchanged when target type is float`
- [x] `it returns a bool unchanged when target type is bool`
- [x] `it returns an array unchanged when target type is array`
- [x] `it converts a backed-enum stored value to the enum instance via tryFrom`
- [x] `it throws InvalidConfigValueException when the stored value is a string but target type is int`
- [x] `it throws InvalidConfigValueException when a backed enum tryFrom returns null`
- [x] `it throws InvalidConfigValueException carrying the key, raw value, and declared type in the message`

## Acceptance Criteria
- `ValueCaster::cast(mixed $rawValue, ConfigDefinition $definition): mixed`
- All exception paths produce `InvalidConfigValueException` (no `\TypeError` leaking)
- PHPStan level 8 clean
- `@throws` tag present

## Implementation Notes
(Left blank — filled in by programmer)
