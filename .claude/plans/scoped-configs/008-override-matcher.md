# Task 008: `OverrideMatcher`

**Status**: pending
**Depends on**: 002, 004
**Retry count**: 0

## Description
Adapt the existing scope-resolution candidate logic (`SignatureCandidateEnumerator` from `markommerce/scope`) to operate over a single row's `overrides` map (keyed by `ScopeSignature::toString()`) rather than an `HasScopesInterface` entity. Given a row + the property's declared axes + the current `ScopeContext`, return the first (most-specific) signature/value match — or null if no override applies.

## Context
- Reference: `packages/scope/src/Resolution/ScopeWalker.php` for the candidate-iteration shape we're mirroring
- The enumerator from scope produces ordered candidate `ScopeSignature`s (most-specific first) given axes + context
- For each candidate, check if its `toString()` exists as a key in the row's `overrides` map; if yes, return that override's raw value
- If the row's `overrides` map is empty or no candidate matches, return null (resolver then falls back to global value, then PHP default)
- Reuse `SignatureCandidateEnumerator` from `markommerce/scope` directly — inject it as a constructor dependency

## Requirements (Test Descriptions)
- [ ] `it returns null when the row has no overrides`
- [ ] `it returns null when no candidate signature matches any override key`
- [ ] `it returns the override value for an exact context match on a single-axis property`
- [ ] `it returns the most-specific composite override when both composite and single-axis overrides exist`
- [ ] `it walks axis hierarchy via the candidate enumerator so a parent-scope override matches when no exact-leaf override exists`
- [ ] `it ignores overrides whose signature references axes not declared on the property`

## Acceptance Criteria
- `OverrideMatcher::match(ConfigRow $row, list<string> $axes, ScopeContext $context): mixed` — returns the raw (still-encrypted-if-secret, still-JSON-decoded) value, or null
- Uses the *injected* `SignatureCandidateEnumerator` — does not duplicate enumeration logic
- PHPStan level 8 clean
- Tests use a `FakeScopeContext` + `FakeScopeRegistry` (in-memory) — no mocks

## Implementation Notes
(Left blank — filled in by programmer)
