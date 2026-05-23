# Task 004: `ConfigDefinition` + `ConfigRow` value objects

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create two immutable value objects: `ConfigDefinition` (registry entry — the canonical metadata about one config field) and `ConfigRow` (storage projection — the hydrated form of one row from `config_values`). Both are `readonly class` per project rules. These are the data contracts that flow between registry, resolver, storage, and writer.

## Context
- Pattern reference: existing value objects in scope (e.g., `ScopeWalkResult`); use `readonly class` + constructor property promotion
- `ConfigDefinition` carries: `key`, `configClass` (FQN of original class), `field` (property name), `axes` (list<string>, from `#[Scoped]`, empty when unscoped), `type` (declared PHP type, normalized to a string like `"int"`, `"string"`, `"App\Enum\Color"`), `defaultValue` (mixed, from the property's declared default), `secret` (bool)
- `ConfigRow` carries: `key`, `value` (mixed — the global, JSON-decoded), `overrides` (`array<string, mixed>` keyed by `ScopeSignature::toString()`), `version` (int), optional `updatedAt` (DateTimeImmutable)
- Both must be JSON-serializable in a stable shape (for diagnostic dumps / debugging — actual storage encoding lives in the driver)
- `ConfigRow` must expose helpers: `withGlobal(mixed $value)`, `withoutGlobal()`, `withOverride(string $signature, mixed $value)`, `withoutOverride(string $signature)`. Each returns a NEW row with `version` unchanged (the writer bumps version separately on save)

## Requirements (Test Descriptions)
- [ ] `it constructs a ConfigDefinition with key, configClass, field, axes, type, defaultValue, and secret`
- [ ] `it exposes ConfigDefinition properties as public read-only via asymmetric visibility or readonly class`
- [ ] `it constructs a ConfigRow with key, value, overrides, version, and updatedAt`
- [ ] `it returns a new ConfigRow with the global value replaced via withGlobal`
- [ ] `it returns a new ConfigRow with the global cleared to null via withoutGlobal`
- [ ] `it returns a new ConfigRow with an override added or replaced via withOverride keyed by signature string`
- [ ] `it returns a new ConfigRow with a specific override removed via withoutOverride`
- [ ] `it preserves the version on all with* mutations so the writer controls version bumps explicitly`

## Acceptance Criteria
- Both classes are `readonly class`
- `declare(strict_types=1);` present
- Non-`final` per project rule
- Constructor uses promoted properties
- All array types are documented with PHPDoc generics (`array<string, mixed>`, `list<string>`)
- PHPStan level 8 clean

## Implementation Notes
(Left blank — filled in by programmer)
