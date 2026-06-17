# Task 008: `AttributeTypeRegistry`

**Status**: done
**Depends on**: 002, 003
**Retry count**: 0

## Description
Implement the registry that holds registered `AttributeTypeInterface` instances keyed by their
`code()`. Built-in types are registered into it at boot (task 013); downstream code can register
or override types. Lookups for an unknown code throw loudly.

## Context
- Pattern: `Markommerce\Config\Registry\ConfigRegistry` (lookup methods).
- **WARNING — do NOT copy `CategorySortOrderRegistry::register()` verbatim.** That registry
  *silently ignores* a duplicate key (`if ($this->has(...)) { return; }`) — the OPPOSITE of what
  this task needs. `AttributeTypeRegistry::register()` must **override** an existing code (last
  registration wins) so a downstream Preference can swap a built-in type (the failing test in
  task 013 depends on this). Key by `code()` in a simple `array<string, AttributeTypeInterface>`
  and assign unconditionally.
- Place in `packages/attribute/src/Registry/AttributeTypeRegistry.php`.
- Methods:
  - `register(AttributeTypeInterface $type): void` — keyed by `code()`; later registration of
    the same code **overrides** (enables Preference-style swaps).
  - `get(string $code): AttributeTypeInterface` — `@throws UnknownAttributeTypeException`.
  - `has(string $code): bool`.
  - `all(): array<string, AttributeTypeInterface>`.
- Registered as a singleton (wired in task 013).

## Requirements (Test Descriptions)
- [x] `it registers a type and retrieves it by its code`
- [x] `it reports whether a type code is registered`
- [x] `it overrides a previously registered type with the same code`
- [x] `it throws UnknownAttributeTypeException when getting an unregistered code`
- [x] `it returns all registered types keyed by code`

## Acceptance Criteria
- Override semantics allow Preference-based swapping of a built-in type.
- Unknown lookups throw `UnknownAttributeTypeException`.

## Implementation Notes
- `AttributeTypeRegistry` placed at `packages/attribute/src/Registry/AttributeTypeRegistry.php`
- Private `array<string, AttributeTypeInterface> $types` keyed by `code()`; `register()` assigns unconditionally (override semantics)
- `get()` throws `UnknownAttributeTypeException::forType($code)` for missing keys
- Tests in `packages/attribute/tests/Unit/Registry/AttributeTypeRegistryTest.php`; all 5 pass
