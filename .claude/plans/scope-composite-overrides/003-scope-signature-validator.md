# Task 003: ScopeSignatureValidator

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Build `ScopeSignatureValidator` — validates that a `ScopeSignature` is legal for a given attribute's declared axes and the registry's known hierarchy values. Used by `ScopeResolver::setOverride` (task 008) before any write to storage. Cached per `(signature-string, attributeAxes-tuple-key)` since the same signature is typically written many times across entities.

Defines `InvalidSignatureForAttributeException` for the "signature mentions axis not in attribute" and "signature value not in registry hierarchy" cases.

## Context
- New file: `packages/scope/src/Signature/ScopeSignatureValidator.php`
- New exception: `packages/scope/src/Exceptions/InvalidSignatureForAttributeException.php` extending `MarkoException` with `message` / `context` / `suggestion` (project Exception Standards).
- Distinct from `InvalidSignatureException` (task 001) which is "the string itself is malformed". This validator handles "the well-formed signature is wrong *for this attribute*".
- Validator takes `ScopeRegistryInterface` in the constructor (constructor injection only — CLAUDE.md §2).
- Validation rules (spec Requirement 5):
  1. Every axis in the signature MUST appear in the attribute's declared axes list. Else throw with the offending axis name and the allowed axis list.
  2. Every `(axis, value)` pair in the signature MUST have the value declared in the registry's hierarchy for that axis. Use `$registry->getHierarchy($axis)->exists($value)`. Else throw with the offending pair and the axis name.
- Cache field on the validator: `private array $cache = []` keyed by `$sig->toString() . '||' . implode(',', $attributeAxes)`. Stores `true` once validated successfully; failures throw immediately and are never cached.

## Requirements (Test Descriptions)
- [ ] `it accepts a signature whose axes are a subset of the attribute axes`
- [ ] `it accepts a signature equal to the full attribute axes set`
- [ ] `it throws InvalidSignatureForAttributeException when the signature mentions an axis not in the attribute axes (case 16)`
- [ ] `it throws InvalidSignatureForAttributeException when the signature value does not exist in the registry hierarchy for that axis (case 17)`
- [ ] `it includes the offending axis name in the exception message when the axis is not in attribute axes`
- [ ] `it includes the offending value and axis in the exception message when the value is not in hierarchy`
- [ ] `it memoizes successful validations so the second call does not re-check the registry (verified via instrumented registry)`
- [ ] `it does not cache failures (a failing validation re-throws on repeat)`

## Acceptance Criteria
- All requirements have passing tests.
- `InvalidSignatureForAttributeException` has named static factory methods (e.g. `forUnknownAxis`, `forUnknownValue`).
- Validator has constructor-injected `ScopeRegistryInterface` (parameter name `scopeRegistry` per CLAUDE.md §3).
- The validator is suitable for binding as a singleton (no per-call mutable state besides the cache; cache is process-local).
- `phpcs`, `php-cs-fixer --dry-run`, `phpstan` clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
