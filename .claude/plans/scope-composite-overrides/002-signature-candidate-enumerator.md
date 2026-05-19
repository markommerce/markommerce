# Task 002: SignatureCandidateEnumerator + ScopeHierarchy walkUp Memoization

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Build `SignatureCandidateEnumerator` — the shared component that produces the bounded, descending-score-ordered list of candidate `ScopeSignature` objects for a given attribute axes + context + registry. This is the **algorithmic heart** of the refactor: both the PHP walker (task 004) and the Postgres COALESCE chain builder (task 011 / 013) consume its output. Two callers ⇒ one source of truth ⇒ no algorithmic drift between PHP and SQL paths.

Also add memoization to `ScopeHierarchy::walkUp()` — called many times during enumeration (once per axis per call), trivially cacheable since the hierarchy is immutable.

## Context
- New file: `packages/scope/src/Signature/SignatureCandidateEnumerator.php`
- Constructor: `public function __construct(private readonly ScopeRegistryInterface $scopeRegistry, private readonly int $cap = 256)`. The enumerator owns the registry (used to call `getHierarchy($axis)->walkUp($path)`). Cap is a constructor arg.
- New class is NOT `readonly` (it has a memoization cache as a mutable property). Constructor properties that *are* immutable stay as constructor-promoted readonly. The cache field is a plain `private array`.
- Method signature: `enumerate(array $attributeAxes, ScopeContext $context): array` returning `list<ScopeSignature>` in descending-score order. The walker (task 004) and the query specs (task 013) both call this method.
- Memoization cache key derivation:
  1. Take the `$attributeAxes` list as-is (order matters — priority).
  2. Take `$context->state()` and `ksort()` it before serializing (so the cache key is stable regardless of `in()` call order).
  3. Cache key = `serialize($attributeAxes) . '||' . serialize($ksortedState)`.
- `ScopeContext` has no `state()` method today. Add one as part of this task:
  ```php
  /** @return array<string, string> the full axis-name → active-path map */
  public function state(): array { return $this->state; }
  ```
  This MUST return the full map (not just `array_keys`), so that changing the active path for an axis changes the cache key.
- Cap defaults to **256**, settable via constructor argument.
- When cap is exceeded during enumeration, fire a warning (`trigger_error("…", E_USER_WARNING)`) ONCE per `enumerate()` invocation, then truncate. Do not throw.
- Enumeration order (verified against behavioral cases 3/4/5/10/11 in the plan):
  ```
  for vA in (walkUp(axisA), OMIT):   // deepest-first
    for vB in (walkUp(axisB), OMIT):
      ...
      skip if all axes OMIT
      emit ScopeSignature
  ```
  This iteration is lexicographically descending on the score tuple in declared-axis-priority order.
- If an axis appears in `attributeAxes` but is NOT set in `$context`, that axis effectively becomes "OMIT-only" — its loop has exactly ONE iteration emitting OMIT (NOT zero iterations — zero would skip the axis entirely and yield incomplete signatures). Signatures mentioning that axis cannot match, so they are never generated.
- `ScopeHierarchy::walkUp()` memoization is per-(axis, path). Since `ScopeHierarchy` is per-axis, the cache key is just `$path`. Cache field: `private array $walkUpCache = []`. Lazy population on first call.
- **`ScopeHierarchy` refactor**: convert from `readonly class` to a plain `class`. The existing `$pathMap` and `$paths` properties become plain `private array` (NOT readonly — PHP allows readonly only on typed promoted properties, not on body-assigned ones). The new `$walkUpCache` field is `private array $walkUpCache = []`. Existing public API and constructor behavior MUST remain identical (no semantic change beyond memoization).
- New exception `EnumerationCapExceededException`? No — a warning is sufficient (cap is not an error).

## Requirements (Test Descriptions)

### SignatureCandidateEnumerator
- [x] `it returns an empty list when no axes are declared`
- [x] `it returns an empty list when context has no active axes for any declared axis (only the empty signature would be emitted and that is skipped)`
- [x] `it emits a single-axis signature when one axis is declared and context has one value at the root`
- [x] `it emits walk-up signatures deepest-first when one axis is declared and context value has ancestors`
- [x] `it emits the cartesian product of walk-up values across two axes`
- [x] `it emits signatures in descending-score order matching the declared axis priority (case 11: channel:b2b before locale:es.es)`
- [x] `it emits the most-specific composite first then progressively less specific ones (case 10)`
- [x] `it skips the empty signature (all axes OMIT)`
- [x] `it handles three axes producing correctly ordered partial and full compositions (case 12)`
- [x] `it caps the candidate list at the configured cap`
- [x] `it emits exactly one E_USER_WARNING per enumerate() call when the cap is exceeded`
- [x] `it uses the default cap of 256 when no cap is configured`
- [x] `it memoizes results for repeated calls with the same attributeAxes and context state`
- [x] `it returns a fresh list when the context state has changed since the last call (different active path for the same axis set)`
- [x] `it returns the same cached list regardless of the order in which axes were added to ScopeContext (cache key is ksorted before serialization)`
- [x] `it returns ScopeSignature objects with axes alphabetically sorted regardless of declaration order`
- [x] `it emits OMIT exactly once for an attribute axis not present in context (the axis contributes one OMIT iteration, not zero — composites without that axis still emit)`

### ScopeHierarchy walkUp memoization
- [x] `it returns the same walked list on repeated calls for the same path`
- [x] `it memoizes the walkUp result so the second call does not recompute (verified via instrumented child class)`
- [x] `it caches independently per path`

### ScopeContext::state accessor (added as part of this task)
- [x] `it exposes the full active-state map (axis-name → active-path) via the state() method, not just keys`
- [x] `it returns an empty array when no axes are active`
- [x] `it returns a different map after the active path for an existing axis is changed via in()`

## Acceptance Criteria
- All requirements have passing tests.
- The enumerator's output ordering is verified against behavioral cases 10 and 11 from the plan (those exact scenarios as fixtures).
- The cap warning fires via `trigger_error(…, E_USER_WARNING)` and is asserted with Pest's `set_error_handler` pattern (no PSR-3 dependency added).
- `phpcs`, `php-cs-fixer --dry-run`, `phpstan` clean.

## Implementation Notes
- `SignatureCandidateEnumerator` at `packages/scope/src/Signature/SignatureCandidateEnumerator.php` uses recursive cartesian enumeration with an `null` OMIT sentinel appended to each axis's walkUp list. All-OMIT combinations are skipped. Cap exceeded fires `E_USER_WARNING` once per call then stops.
- `ScopeHierarchy` converted from `readonly class` to plain `class` to allow the mutable `$walkUpCache` field. Public API unchanged.
- `ScopeContext::state()` method added returning the full `array<string, string>` map.
- Cache key uses `ksort()` on context state so insertion order in `in()` calls doesn't affect key equality.
