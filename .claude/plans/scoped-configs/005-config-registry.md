# Task 005: `ConfigRegistry` + `ConfigRegistryBuilder`

**Status**: pending
**Depends on**: 002, 003, 004
**Retry count**: 0

## Description
Build the in-memory registry of all known config definitions and the builder that scans a list of config classes via reflection. The registry indexes definitions two ways: by `(configClass, field)` for the resolver's typed access path, and by `key` for the string-keyed resolver / CLI path. Builder enforces uniqueness on `key` across classes (conflicts throw `ConfigKeyConflictException`) and validates that each `#[Scoped]` axis exists in the global `ScopeRegistryInterface`.

## Context
- Reference: `packages/scope/src/Metadata/ScopeMetadataFactory.php` for the reflection scan pattern
- Builder input: `list<class-string>` (config classes to scan). Discovery of *which* classes to scan is a separate concern (consumed via module.php / DI configuration in later wiring task 020).
- Each property with `#[Config]` becomes one `ConfigDefinition`. Properties may carry `#[Scoped]` too — if absent, definition has empty `axes`.
- Read the declared PHP type via `ReflectionProperty::getType()`. Reject union / intersection types with `InvalidConfigClassException`.
- Read the property's declared default via `ReflectionProperty::getDefaultValue()` (PHP 8+).
- **Default/nullable validation**: every `#[Config]` property must either have a default value OR be nullable. Properties that are non-nullable AND have no default would produce a runtime `TypeError` when the resolver falls back to null, so they must be rejected at registry build time. Throw `InvalidConfigClassException::propertyMissingDefaultOrNullability(...)` (extend task 002 accordingly).
- **Constructor validation**: the config class must NOT declare a constructor with required (non-defaulted) parameters. The codegen subclass (task 014) supplies only a `ConfigResolver` to its own constructor; required parent-constructor args cannot be threaded through. Throw `InvalidConfigClassException::configClassHasRequiredConstructor(...)` at build time so the failure happens before codegen.
- Axis validation: at build time, call `ScopeRegistryInterface::hasAxis($axis)` for each declared axis; throw `AxisNotDeclaredException::forAxisOnProperty(...)` if missing.
- `ConfigRegistry` is a read-only lookup; builder produces a frozen registry instance.

## Requirements (Test Descriptions)
- [ ] `it builds a registry from a single config class with one #[Config] property`
- [ ] `it builds a registry from multiple config classes`
- [ ] `it captures the property's declared PHP type as a normalized string in ConfigDefinition`
- [ ] `it captures the property's default value in ConfigDefinition`
- [ ] `it captures axes from #[Scoped] when present and uses an empty axes list when absent`
- [ ] `it throws ConfigKeyConflictException when two properties declare the same #[Config(key)]`
- [ ] `it throws AxisNotDeclaredException when a property's #[Scoped] axis is not in the ScopeRegistry`
- [ ] `it throws InvalidConfigClassException when a #[Config] property has a union or intersection type`
- [ ] `it throws InvalidConfigClassException when a #[Config] property is non-nullable and has no default value`
- [ ] `it throws InvalidConfigClassException when the config class declares a constructor with at least one required parameter`
- [ ] `it accepts a config class whose constructor has only optional/defaulted parameters`
- [ ] `it throws ConfigNotFoundException when registry.definition(class, field) is called for unknown fields`
- [ ] `it returns a definition by string key via registry.byKey(key)`

## Acceptance Criteria
- `ConfigRegistry` exposes: `definition(string $configClass, string $field): ConfigDefinition`, `byKey(string $key): ConfigDefinition`, `all(): list<ConfigDefinition>`
- `ConfigRegistryBuilder::build(list<class-string> $configClasses, ScopeRegistryInterface $scopeRegistry): ConfigRegistry`
- All `@throws` tags accurate
- PHPStan level 8 clean
- Tests use fakes (in-memory `FakeScopeRegistry` implementing `ScopeRegistryInterface`), not mocks

## Implementation Notes
(Left blank — filled in by programmer)
