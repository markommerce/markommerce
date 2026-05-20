# Task 004: Collapse Default-Resolved Axes in SignatureCandidateEnumerator and ScopeWalker

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Changes `SignatureCandidateEnumerator::enumerate()` AND `ScopeWalker::findFirstMatch()` to filter the axis default out of `ScopeHierarchy::walkUp()` results. The enumerator change makes `walk()` / `resolved()` / `ScopedOrderBy` short-circuit to a plain column when every axis collapses to OMIT-only — when the candidate list is empty, the existing short-circuits in `ScopedOrderBy::apply()` and `PgSqlScopedFieldRenderer::render()` emit a plain column with no `scopes` JSON access. The `ScopeWalker::findFirstMatch()` change applies the same filter to `walkAt()` / `resolvedAt()`, which take a single signature directly and don't go through the enumerator. Both filters together deliver the "default scope ≡ base column" invariant from every resolution path.

## Context

Current code (`packages/scope/src/Signature/SignatureCandidateEnumerator.php:44-54`):
```php
foreach ($attributeAxes as $axis) {
    $path = $state[$axis] ?? null;
    if ($path !== null && $this->scopeRegistry->hasAxis($axis)) {
        $walked = $this->scopeRegistry->getHierarchy($axis)->walkUp($path);
        $axisValues[$axis] = array_merge($walked, [null]);
    } else {
        $axisValues[$axis] = [null];
    }
}
```

After the change:
```php
foreach ($attributeAxes as $axis) {
    $path = $state[$axis] ?? null;
    if ($path !== null && $this->scopeRegistry->hasAxis($axis)) {
        $axisDefault = $this->scopeRegistry->getAxis($axis)->default;
        $walked = $this->scopeRegistry->getHierarchy($axis)->walkUp($path);
        $walked = array_values(array_filter($walked, fn (string $p): bool => $p !== $axisDefault));
        $axisValues[$axis] = array_merge($walked, [null]);
    } else {
        $axisValues[$axis] = [null];
    }
}
```

Filtering inside `walkUp()` results (not just the leaf value) covers the case where a non-default child path walks up *through* the default — e.g., a hypothetical `default.en` would walk up to `['default.en', 'default']` and only the default needs filtering. The existing all-OMIT skip in `cartesian()` (`SignatureCandidateEnumerator.php:88-92`) then naturally returns an empty list when every axis collapsed.

`ScopeContext` is unchanged. Setting `in('locale', 'default')` remains valid (the default scope is still a declared scope), and an unset axis is already OMIT — the new filter makes both cases observationally equivalent.

- Files to modify:
  - `packages/scope/src/Signature/SignatureCandidateEnumerator.php`
  - `packages/scope/src/Resolution/ScopeWalker.php` — apply the **same axis-default filter** inside `findFirstMatch()` (the `walkAt`/`resolvedAt` code path). `walkAt` does not go through the enumerator; it walks up explicitly via `$registry->getHierarchy($axis)->walkUp($path)`. Without filtering here, a stored override at `locale:default` (legitimately writable today via the trait's `setOverride()` bypass, or pre-existing data from before task 005's validator rule lands) would be returned by `walkAt`/`resolvedAt`, contradicting the "default scope ≡ base column" invariant. Filter: `$walked = array_values(array_filter($walked, fn (string $p): bool => $p !== $registry->getAxis($axis)->default));`.
  - `packages/scope/tests/Unit/Signature/SignatureCandidateEnumeratorTest.php` (existing tests assume `walkUp` results are not filtered — review every assertion and update fixtures so the axis defaults declared on fake registries match the new behaviour expectations)
  - `packages/scope/tests/Unit/Resolution/ScopeWalkerTest.php` — task 001's ripple already extended `makeWalkerRegistry()` with the sentinel-default convention (`array $defaults = []`, falling back to `'__test_default'`). Add a test asserting that `walkAt` with a signature naming an axis at its default returns `notFound` even when an override happens to be stored at `axis:default` (covers the bypass case). Add a second test where a non-default path walks up *through* the default (e.g. `walkUp('default.en')` yields `['default.en', 'default']`) to confirm the filter strips the default ancestor.
- Tests must use a `ScopeRegistryInterface` fake that returns `ScopeAxis` instances carrying a `default` (task 001 has already updated fakes to pass the third constructor argument; this task can rely on that ripple).
- Patterns to follow:
  - PHP 8.5 `array_filter` over `foreach` (CLAUDE.md key rule #13).
  - Existing memoization on `$cacheKey` keeps working — no change to the cache layer.

## Requirements (Test Descriptions)
- [x] `it omits an axis whose context value equals the axis default scope`
- [x] `it filters the default scope out of hierarchy walk-up results`
- [x] `it returns an empty candidate list when every attribute axis is at its default`
- [x] `it enumerates candidates normally for axes at non-default scopes`
- [x] `it enumerates partial composites when some axes are default and others are not`
- [x] `it returns an empty list for a single-axis attribute resolved to its default`
- [x] `walkAt returns notFound for a signature at the axis default even when a stored override exists at axis:default` (covers `ScopeWalker::findFirstMatch` filter)
- [x] `walkAt skips a default ancestor while still matching a non-default descendant override during walk-up` (covers the filter behaviour for paths walking up through the default)

## Acceptance Criteria
- All requirements have passing tests.
- The memoization cache still keys on attribute axes + context state.
- `ScopeWalker::findFirstMatch()` applies the same `array_filter` step against the axis default; `walkAt`/`resolvedAt` never returns a value stored at `axis:default`.
- `composer test` for the `scope` package is green. **Do not gate on `composer test:all`** — `scope-pgsql`'s integration test stays red until task 007 lands.
- `phpstan analyse` clean for the enumerator, the walker, and their callers.
- Code follows code standards.

## Implementation Notes
- Added `getAxis($axis)->default` filter in `SignatureCandidateEnumerator::enumerate()` — strips the axis default from the `walkUp()` result before building axis value lists. When every value is stripped, the axis collapses to OMIT-only, and the existing all-OMIT skip in `cartesian()` returns an empty list.
- Added the same `getAxis($axis)->default` filter in `ScopeWalker::findFirstMatch()` — strips the default from the `walkUp()` result before `array_find`. Ensures `walkAt`/`resolvedAt` never return a stored value at `axis:default`.
- Existing `makeWalkerRegistry()` and `makeRegistry()` fakes already use `__test_default` sentinel convention from Task 001. New tests use `__test_default` as the default and verify the filter works both when the context value IS the default (→ notFound) and when a non-default child walks through the default ancestor (→ default stripped, non-default match found).
- All 573 tests pass. PHPStan level 8 clean for both modified files.
