# Task 008: ScopeResolver — Composite Write API with Validation

**Status**: pending
**Depends on**: 003, 006, 007
**Retry count**: 0

## Description
Wire `ScopeSignatureValidator` (task 003) into `ScopeResolver`'s write path. Every call to `setOverride(Entity, prop, value, ScopeSignature)` validates the signature against the attribute's declared axes and the registry's hierarchy BEFORE the value reaches storage. Same for `clearOverride`. Reads stay unvalidated (defensive-ignore is automatic per task 004).

Covers behavioral cases 16 and 17 from the brief.

## Context
- File: `packages/scope/src/Resolver/ScopeResolver.php`.
- Inject `ScopeSignatureValidator` via the constructor. Parameter name `scopeSignatureValidator` (per CLAUDE.md §3 — camelCase of interface/class name).
- In `setOverride`:
  ```
  // existing: assertScopedAndFindStorage(...)
  attributeAxes = scopeMetadataFactory.for(get_class($entity)).axesForProperty($property)
  scopeSignatureValidator.validateForAttribute($signature, $attributeAxes)  // throws on bad
  storage.setOverride($signature.toString(), $property, $value)
  ```
- In `clearOverride`:
  ```
  attributeAxes = scopeMetadataFactory.for(get_class($entity)).axesForProperty($property)
  scopeSignatureValidator.validateForAttribute($signature, $attributeAxes)
  storage.clearOverride($signature.toString(), $property)
  ```
- `resolvedAt(Entity, prop, ScopeSignature)` does NOT validate — it delegates to `ScopeWalker::walkAt()` which already enforces single-axis. (If the user passes a multi-axis signature, walkAt throws `MultiAxisWalkAtNotSupportedException` — that's the correct UX.)
- `resolved(Entity, prop)` is unchanged in semantics — context-driven, no signature input. **NOTE**: task 004 changed `ScopeWalker::walk()` to drop its `$registry` argument; the resolver was updated in task 004 already to match. This task does not change `resolved()`'s call shape.
- Update `packages/scope/module.php` to register `ScopeSignatureValidator` as a singleton and inject it into the `ScopeResolver` constructor.
- Update existing `ScopeResolverTest.php` cases:
  - Existing tests already pass `ScopeSignature` after task 006. Now add validator wiring to the test factory `makeResolver()`.
  - Existing tests use the `'store'` axis with hierarchy `['global', 'global.us']` — these are valid signatures, validation passes silently.

## Requirements (Test Descriptions)
- [ ] `setOverride writes the value to storage under the signature's canonical string key`
- [ ] `setOverride throws InvalidSignatureForAttributeException when the signature mentions an axis not in the attribute axes (case 16)`
- [ ] `setOverride throws InvalidSignatureForAttributeException when the signature value is not in the registry hierarchy (case 17)`
- [ ] `setOverride accepts a single-axis signature for a single-axis attribute`
- [ ] `setOverride accepts a two-axis composite signature for a two-axis attribute`
- [ ] `setOverride accepts a partial signature (subset of the attribute axes)`
- [ ] `clearOverride removes the entry under the signature's canonical string key`
- [ ] `clearOverride throws InvalidSignatureForAttributeException for an invalid signature`
- [ ] `resolved continues to use the active ScopeContext without requiring a signature`
- [ ] `resolvedAt delegates to walkAt which throws on multi-axis signatures`
- [ ] `the resolver does not call the validator on the read (resolved / resolvedAt) path (verified via instrumented validator)`

## Acceptance Criteria
- All requirements have passing tests.
- `ScopeResolver` has constructor-injected `ScopeMetadataFactory`, `ScopeWalker`, `ScopeContext`, `ScopeSignatureValidator` — exactly those four, no extras.
- `packages/scope/module.php` registers the validator as a singleton.
- `composer test` is green.
- `phpcs`, `php-cs-fixer --dry-run`, `phpstan` clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
