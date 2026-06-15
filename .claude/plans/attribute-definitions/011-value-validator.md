# Task 011: Attribute value validator/caster

**Status**: done
**Depends on**: 002, 003, 004, 005, 006, 007, 008
**Retry count**: 0

> Note: builds against `AttributeDefinitionInterface` (task 003), NOT the `AttributeDefinition`
> entity (task 009) — so unit tests use a lightweight fake definition, no entity/DB needed.

## Description
Implement `AttributeValueValidator` — the orchestrator that, given an `AttributeDefinition`
(any `AttributeDefinitionInterface`), a raw value, and (for select types) the allowed option
values, validates and casts the value using the appropriate type from the
`AttributeTypeRegistry`. Enforces `required` and routes option membership to the
select/multiselect types. This is the single entry point Phase 2 will call before persisting a
value.

## Context
- Place in `packages/attribute/src/Validation/AttributeValueValidator.php`.
- Inject `AttributeTypeRegistry`. Resolve the type via `$definition->type()`.
- Method signature: `validate(AttributeDefinitionInterface $definition, mixed $raw, array
  $allowedOptions = []): mixed` where `$allowedOptions` is a `list<string>` of option `value`
  strings. Typehint the **interface** (task 003), never the entity — keeps the validator
  unit-testable with a lightweight fake definition and no DB.
- **Options contract (FIXED — shared with task 006):** before delegating to `cast()`, the
  validator merges `$allowedOptions` into the definition's `config()['options']` so the
  select/multiselect types read them from `$definition->config()['options']`. Because the
  definition may be an immutable entity/value object, do this by passing a definition view whose
  `config()` returns the merged array (e.g. a small read-only decorator implementing
  `AttributeDefinitionInterface`) — do NOT mutate the original. The validator itself does NOT
  query the option repository; the caller (Phase 2 / the service) supplies `$allowedOptions`.
- Behavior:
  - Resolve the type; unknown type → `UnknownAttributeTypeException` (from the registry).
  - `required` definition + null/empty value → `InvalidAttributeValueException`.
  - Non-required + null → null is allowed (no cast).
  - Otherwise delegate to `$type->cast($raw, $definitionView)`.
- This task does NOT touch persistence — it returns the cast value or throws.

## Requirements (Test Descriptions)
- [x] `it casts a value using the type resolved from the definition`
- [x] `it throws when a required attribute receives a null value`
- [x] `it allows null for a non-required attribute`
- [x] `it enforces option membership for a select definition`
- [x] `it propagates InvalidAttributeValueException from the underlying type`
- [x] `it throws UnknownAttributeTypeException when the definition type is not registered`

## Acceptance Criteria
- Single, reusable validation entry point covering all eight types via the registry.
- Errors are the loud domain exceptions from task 002.

## Implementation Notes
- `AttributeValueValidator` in `src/Validation/` delegates to `AttributeTypeRegistry::get()` then `AttributeTypeInterface::cast()`.
- `DefinitionWithOptions` (read-only decorator in same namespace) merges `$allowedOptions` into the definition's `config()['options']` without mutating the original, passed to `cast()` only when `$allowedOptions` is non-empty.
- Required + null check precedes the null short-circuit; non-required null returns `null` directly without delegating to the type.
- Requirement 5 (propagate `InvalidAttributeValueException`) and Requirement 6 (`UnknownAttributeTypeException`) both passed immediately — the exceptions propagate naturally from the registry and type implementations, no extra code was needed.
