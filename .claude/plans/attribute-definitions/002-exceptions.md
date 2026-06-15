# Task 002: Attribute exceptions

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Create the domain exceptions for the attribute kernel, each extending `MarkoException` with
static factory methods and named `message`/`context`/`suggestion` parameters. These are
referenced by the type implementations, registry, and definition service.

## Context
- Pattern: `packages/config/src/Exceptions/InvalidConfigValueException.php` and
  `SecretCipherException.php` — extend `Marko\Core\Exceptions\MarkoException`, static factories,
  named args. No `final`.
- Place in `packages/attribute/src/Exceptions/`.
- Classes to create:
  - `InvalidAttributeValueException` — `forValue(string $code, string $type, string $raw)`.
  - `UnknownAttributeTypeException` — `forType(string $typeCode)`.
  - `DuplicateAttributeCodeException` — `forCode(string $entityType, string $code)`.
  - `ReservedAttributeCodeException` — `forCode(string $entityType, string $code)`.
  - `AttributeDefinitionNotFoundException` — `forCode(string $entityType, string $code)`.
  - `InvalidAttributeOptionException` — `forValue(string $code, string $optionValue)`.

## Requirements (Test Descriptions)
- [x] `it builds InvalidAttributeValueException with message context and suggestion`
- [x] `it builds UnknownAttributeTypeException naming the unknown type code`
- [x] `it builds DuplicateAttributeCodeException naming the entity type and code`
- [x] `it builds ReservedAttributeCodeException naming the entity type and code`
- [x] `it builds AttributeDefinitionNotFoundException naming the entity type and code`
- [x] `it builds InvalidAttributeOptionException naming the attribute code and option value`

## Acceptance Criteria
- All exceptions extend `MarkoException` and expose non-empty `message`, `context`, `suggestion`.
- Factory method names describe the specific error condition.

## Implementation Notes
- All 6 exception classes created in `packages/attribute/src/Exceptions/`.
- Each extends `MarkoException` with a static factory method using named arguments for `message`, `context`, and `suggestion`.
- No `final` keyword used; all classes are open for Marko Preferences.
- Tests in `packages/attribute/tests/Unit/Exceptions/ExceptionsTest.php` — all 6 pass (18 total in the attribute package pass).
