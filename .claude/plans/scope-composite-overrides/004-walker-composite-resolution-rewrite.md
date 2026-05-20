# Task 004: ScopeWalker — Composite Resolution Rewrite

**Status**: complete
**Depends on**: 001, 002
**Retry count**: 0

## Description
Rewrite `ScopeWalker::walk()` to use the new performance-first algorithm: enumerate candidate signatures via `SignatureCandidateEnumerator`, look each up in storage by string key, return the first hit. This **replaces** the current "iterate per-axis walks, first-axis-wins" implementation.

This is the **largest task** in the plan and covers behavioral cases 1–13 from the brief. Strict TDD: write the failing test for each case, then make it pass, then move to the next.

## Context
- File: `packages/scope/src/Resolution/ScopeWalker.php` (REWRITTEN).
- Old algorithm (delete): iterate `$axes`, for each one `walkUp` and check `hasOverride('axis:path', $property)`, return first match.
- New algorithm:
  ```
  candidates = enumerator.enumerate(attributeAxes, context)
  foreach candidates as sig:
      sigString = sig.toString()
      if overrides.hasOverride(sigString, $property):
          return ScopeWalkResult::found(overrides.override(sigString, $property))
  return ScopeWalkResult::notFound()
  ```
- New `walk()` signature: `walk(HasScopesInterface $overrides, string $property, array $axes, ScopeContext $context): ScopeWalkResult`. **The `$registry` parameter is DROPPED** — the enumerator owns the registry (injected at construction time). `walkAt()` retains its `$registry` parameter because it operates without a context.
- `ScopeResolver::resolved()` is updated in this task to drop the `$registry` argument from its `walk()` call.
- Constructor: `ScopeWalker(private SignatureCandidateEnumerator $signatureCandidateEnumerator)`. Update `packages/scope/module.php` so the enumerator is a singleton and the walker pulls it in.
- `walkAt()` is rewritten in task 005, NOT here. Leave the existing `walkAt()` code in place for this task; it'll be reworked in 005.
- **Every `new ScopeWalker(...)` construction site in `packages/scope/tests/` must pass an enumerator**. Use `new ScopeWalker(new SignatureCandidateEnumerator($registry))` (default cap). Affected test files:
  - `packages/scope/tests/Unit/Resolution/ScopeWalkerTest.php`
  - `packages/scope/tests/Unit/Resolver/ScopeResolverTest.php`
  - any other file that does `new ScopeWalker(`. Verify with `grep -rn 'new ScopeWalker(' packages/scope` returning only constructions that pass an enumerator after task completion.
- The `null` preservation contract (explicit `null` override counts as "found") MUST be preserved — use `array_key_exists` in `hasOverride` implementation (already present in the trait via `array_key_exists` semantics).
- Defensive-ignore behavior is automatic: signatures stored with axes NOT in the attribute's declared axes are NEVER generated as candidates, so they are NEVER looked up. No runtime cost, no explicit skip code needed. (Behavioral case 7's "if encountered on read" requirement is satisfied by construction.)
- Old per-axis tests in `packages/scope/tests/Unit/Resolution/ScopeWalkerTest.php` that exercised "first axis wins" semantics MUST be removed or rewritten — the semantic has changed. Specifically:
  - DELETE: `'walks axes in declared priority order returning the first axis match'`
  - DELETE: `'skips axes that are not set in ScopeContext'` (semantic changed; the equivalent under the new model is "signatures with that axis are never generated")
  - DELETE: `'falls through an axis with no overrides for the property to the next axis (cross-axis fallthrough)'`
  - DELETE: `'stops cross-axis fallthrough when an explicit null is found in an axis (null counts as a found value)'` — REWRITE as "explicit null in a higher-scored signature wins over a real value in a lower-scored signature" (the new semantic equivalent).
  - KEEP (still valid): "returns the override at the current scope when one exists", "returns an ancestor override when no override exists at the current scope", "returns notFound when no override exists at any walked scope", "preserves an explicit null override and does not fall through it within an axis", "returns notFound when no axes are declared and no overrides exist", and all `walkAt` tests (task 005 will rework those).

## Requirements (Test Descriptions)

Behavioral cases (numbered from the task brief — these are THE specification):

- [x] `it matches a single-axis override via hierarchy walk-up (case 1: locale:es matches context locale es.es)`
- [x] `it picks the deeper hierarchy match within an axis (case 2: locale:es.es wins over locale:es when context is es.es)`
- [x] `it picks the most-specific composite when both composite and partials exist (case 3: channel:b2b|locale:es wins over channel:b2b and locale:es)`
- [x] `it picks the higher-priority single-axis when only single-axis overrides exist (case 4: channel:b2b wins over locale:es)`
- [x] `it falls through to lower-priority axis when higher-priority axis has no applicable override (case 5: locale:es wins when only locale:es exists)`
- [x] `it does not match an override mentioning an axis not in the context (case 6: market:eu.es does not match a context without market)`
- [x] `it ignores stored signatures with axes not in the attribute axes (case 7: defensive ignore on read)` — fixture: attribute declared `axes: ['locale']`, storage contains `"market:eu"`, context has `locale: es`. Walker MUST return notFound; the test additionally asserts the walker never called `$overrides->override('market:eu', ...)` (use an instrumented HasScopesInterface that records calls)
- [x] `it returns notFound when no applicable overrides exist (case 8)`
- [x] `it walks hierarchy inside composites (case 9: channel:b2b|locale:es matches context channel b2b, locale es.es)`
- [x] `it scores composites with hierarchy walks (case 10: channel:b2b|locale:es.es wins over channel:b2b|locale:es when context locale is es.es)`
- [x] `it lets higher-priority axis dominate hierarchy depth (case 11: channel:b2b beats locale:es.es when context is {channel: b2b, locale: es.es})`
- [x] `it resolves three-axis composites across full and partial compositions (case 12)`
- [x] `it returns notFound when context is empty even if axes are declared (case 13)`
- [x] `it preserves an explicit null override as found (null-as-found contract preserved)`
- [x] `it does NOT iterate HasScopesInterface::overrides() on the resolution path (verified via instrumented storage that counts overrides() calls)`

## Acceptance Criteria
- All 15 behavioral and contract tests pass.
- The walker constructor takes `SignatureCandidateEnumerator` (constructor injection).
- `walk()` signature is `walk(HasScopesInterface $overrides, string $property, array $axes, ScopeContext $context): ScopeWalkResult` — the `$registry` parameter is dropped.
- `ScopeResolver::resolved()` updated to match the new `walk()` signature (no `$registry` passed).
- `ScopeWalker::walk()` body is approximately the 6-line pseudocode in Context — no axis-iteration loop remains.
- `packages/scope/module.php` registers `SignatureCandidateEnumerator` as a singleton and uses it to construct `ScopeWalker`.
- Tests that asserted the old first-axis-wins semantics are deleted, not skipped.
- `grep -rn 'new ScopeWalker(' packages/scope` shows zero zero-argument constructions.
- `phpcs`, `php-cs-fixer --dry-run`, `phpstan` clean.

## Implementation Notes
- Rewrote `ScopeWalker::walk()` to use `SignatureCandidateEnumerator::enumerate()` — the body is the exact 6-line pseudocode from the spec.
- Dropped `$registry` parameter from `walk()` signature; constructor now takes `SignatureCandidateEnumerator`.
- Updated `ScopeResolver::resolved()` to drop the `$registry` argument from its `walk()` call.
- Removed 4 old per-axis-wins tests; replaced `'stops cross-axis fallthrough...'` with `'preserves an explicit null override as found (null-as-found contract preserved)'`.
- Added all 15 new behavioral and contract tests.
- `walkAt()` left intact (reworked in task 005).
- `module.php` updated to register `SignatureCandidateEnumerator` as a singleton.
- All `new ScopeWalker(...)` calls in tests now pass `new SignatureCandidateEnumerator($registry)`.
- PHPStan, PHPCS, PHP-CS-Fixer all clean on modified files.
