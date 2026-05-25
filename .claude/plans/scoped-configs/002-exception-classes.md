# Task 002: Exception classes

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the full exception hierarchy for `markommerce/config`. Each exception extends Marko's base `MarkoException` (via static factory methods) with the three named parameters `message`, `context`, `suggestion`. No silent failures — every error path the package can produce gets a named, loud exception type with helpful suggestions.

## Context
- Pattern reference: `packages/scope/src/Exceptions/` and project `code-standards.md` exception standards section
- Each factory must produce a fully populated `MarkoException` (`message`, `context`, `suggestion`)
- Add `@throws` PHPDoc on every factory and any caller in this task

## Requirements (Test Descriptions)
- [x] `it creates ConfigNotFoundException with the missing key in message and a how-to-register suggestion`
- [x] `it creates ConfigKeyConflictException when two definitions claim the same key, naming both classes and the duplicate key`
- [x] `it creates InvalidConfigClassException via factory configClassHasRequiredConstructor when a config class has required constructor params`
- [x] `it creates InvalidConfigClassException via factory propertyMissingDefaultOrNullability when a non-nullable #[Config] property has no default`
- [x] `it creates InvalidConfigClassException via factory nonSubclassPreference when a preferred class is not a subclass of the original`
- [x] `it creates InvalidConfigClassException for properties without #[Config] or with unsupported types (union/intersection/readonly)`
- [x] `it creates InvalidConfigValueException carrying the offending key, raw stored value, and target PHP type`
- [x] `it creates ProxyNotGeneratedException with a config:generate suggestion when a typed proxy is missing at runtime`
- [x] `it creates StaleConfigWriteException naming the key and retry count after optimistic-lock retries are exhausted`
- [x] `it creates AxisNotDeclaredException when a write signature references an axis not declared on the property`
- [x] `it creates SecretCipherException via factory notConfigured naming MARKOMMERCE_CONFIG_SECRET_KEY in the suggestion`
- [x] `it creates SecretCipherException via factory sodiumUnavailable / invalidKeyLength / tamperedCiphertext`

## Acceptance Criteria
- All exceptions extend `MarkoException` directly or through a shared `ConfigException` base
- Every static factory is covered by an explicit test asserting the resulting `message`, `context`, and `suggestion` substrings
- All classes are non-`final` per project rules
- `declare(strict_types=1);` everywhere
- PHPStan level 8 clean

## Implementation Notes
(Left blank — filled in by programmer)
