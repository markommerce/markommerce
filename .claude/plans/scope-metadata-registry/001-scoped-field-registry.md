# Task 001: Create ScopedFieldRegistry

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Introduce a new `Markommerce\Scope\Metadata\ScopedFieldRegistry` class that
accumulates property-to-axis mappings at boot time. Each entry maps a
`(entityClass, property)` pair to a list of axes. Registrations from
multiple sources for the same property union their axes. Registering against
an axis not present in `ScopeRegistryInterface`, or against a class that
does not exist (typo catch), fails loudly.

This is the central new abstraction of the plan. Everything downstream
reads from this registry.

The class name keeps "Field" (matching `FEATURES.md`'s bridge vocabulary)
but every method uses "Property" to align with the existing public
`ScopeMetadata` API (`scopedProperties()`, `axesForProperty()`).

## Context
- Related files (read for patterns):
  - `packages/scope/src/Registry/ScopeRegistryInterface.php` — existing axis registry, source of truth for valid axis names
  - `packages/scope/src/Registry/PhpScopeRegistry.php` — example implementation style
  - `packages/scope/src/Exceptions/UnknownAxisException.php` — exception thrown when an axis isn't registered
  - `packages/scope/src/Metadata/ScopeMetadata.php` — value-object pattern to mirror for any internal value objects
- Place the new file at `packages/scope/src/Metadata/ScopedFieldRegistry.php`.
- Place tests at `packages/scope/tests/Unit/Metadata/ScopedFieldRegistryTest.php`.
- Constructor injection only. No traits. `declare(strict_types=1);`. Every throw documented with `@throws`. Constants get explicit types.
- The class is mutable (state accumulates via `register()`), so it cannot be `readonly`. Other classes in this plan that are immutable should be `readonly`.

## Requirements (Test Descriptions)
- [x] `it registers a property with a non-empty list of axes`
- [x] `it returns the registered axes via axesForProperty for the same class and property`
- [x] `it returns an empty list from axesForProperty for an unregistered (class, property) pair`
- [x] `it returns the full property-to-axes map via propertiesFor for a registered entity class`
- [x] `it returns an empty map from propertiesFor for an entity class with no registrations`
- [x] `it reports hasScopedProperties true after at least one registration on a class`
- [x] `it reports hasScopedProperties false for an unregistered class`
- [x] `it unions axes (no duplicates) when the same property is registered twice with overlapping axis lists`
- [x] `it preserves first-registration axis order and appends new axes from later registrations in their declared order`
- [x] `it is a no-op when the same property is registered twice with the exact same axes (idempotent)`
- [x] `it throws UnknownAxisException when register is called with an axis not present in ScopeRegistryInterface`
- [x] `it accepts an empty axis list and treats the registration as a no-op (the property does not appear in propertiesFor and hasScopedProperties stays false if it was the only registration)`
- [x] `it validates every axis in a multi-axis registration so a list mixing valid and unknown axes still throws UnknownAxisException`
- [x] `it throws UnknownEntityClassException when register is called with a class name that does not exist (caught typos in bridge module.php files)`
- [x] `it validates class existence before axis validation so a registration with both a typo class name and an unknown axis surfaces the class error first`

## Acceptance Criteria
- All requirements have passing tests.
- Class is in `Markommerce\Scope\Metadata` namespace, file at `packages/scope/src/Metadata/ScopedFieldRegistry.php`.
- Public method signatures (with PHPDoc list/array shapes explicit for PHPStan level 8):
  - `public function register(string $entityClass, string $property, array $axes): void` — `@param list<string> $axes`, `@throws UnknownAxisException`, `@throws UnknownEntityClassException`
  - `public function axesForProperty(string $entityClass, string $property): array` — `@return list<string>`
  - `public function propertiesFor(string $entityClass): array` — `@return array<string, list<string>>`
  - `public function hasScopedProperties(string $entityClass): bool`
- New exception `Markommerce\Scope\Exceptions\UnknownEntityClassException` extending `MarkoException`, with a static factory method `forClass(string $entityClass): self` whose `message` / `context` / `suggestion` match the project pattern (see `UnknownAxisException` for the exact shape).
- Class-existence check uses `class_exists($entityClass)` (autoloads if needed). Interfaces/traits/enums are accepted as long as the symbol is defined — the registry doesn't enforce "must be an entity"; that's the caller's responsibility.
- Constructor takes a single `ScopeRegistryInterface $scopeRegistry` parameter (with that exact param name per code-standards rule on interface parameter naming).
- Internal storage uses `array<class-string, array<string, list<string>>>` with an explicit `@var` annotation on the property.
- Class is NOT `readonly` (mutable state accumulates via `register()`). Document the boot-time-only mutation convention in the class docblock.
- Class is NOT `final` (project rule — preserves Marko Preferences).
- Every `@throws` documented; exception classes imported (no FQCN in `@throws`).
- PHPStan level 8 clean. No traits used. PHP-CS-Fixer and `phpcs` pass.

## Implementation Notes
- Created `packages/scope/src/Exceptions/UnknownEntityClassException.php` with `forClass(string $entityClass): self` static factory.
- Created `packages/scope/src/Metadata/ScopedFieldRegistry.php` with all four public methods.
- Class-existence check uses `class_exists() || interface_exists() || enum_exists()` to accept all symbol types per spec.
- Internal storage annotated as `array<class-string, array<string, list<string>>>`.
- Axis union merges via `in_array` check to avoid duplicates while preserving order.
- Empty axis list returns early (no-op) before any validation to avoid unnecessary work.
- Class existence validated before axis validation per requirement 15.
- PHP-CS-Fixer and phpcbf auto-fixed formatting; PHPStan level 8 passes with no errors.
