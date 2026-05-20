# Task 007: HasScopesInterface — Rename `$scopeKey` → `$signature`

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Cosmetic rename to align the storage interface vocabulary with the new composite model. The parameter name `$scopeKey` (which historically meant `"axis:path"`) now reads `$signature` (which means a `ScopeSignature::toString()` output — single or composite). Behavior is unchanged; storage still treats the value as an opaque string and uses it as a JSONB key.

This is independent of all algorithmic work and can run in batch 1.

## Context
- Files affected:
  - `packages/scope/src/Storage/HasScopesInterface.php` (rename parameters on `setOverride`, `override`, `hasOverride`, `clearOverride`).
  - `packages/scope/src/Storage/HasScopes.php` (matching trait method signatures).
  - All callers within `packages/scope` and `packages/scope-pgsql` need their named-argument call sites updated if they use `scopeKey: …` (search shows none — calls all use positional args, but verify with `grep -rn 'scopeKey:' packages/`).
  - Tests in `packages/scope/tests/Unit/Storage/HasScopesTraitTest.php` use positional args throughout — no test changes needed for the rename itself; just verify they still pass.
- This task does NOT change behavior, types, or signatures-as-strings format. Just the parameter identifier.
- This task is a breaking change to the public interface ONLY for code that used named arguments (`setOverride(scopeKey: 'locale:es', …)`). The change is acceptable per "breaking change is acceptable" in the brief.

## Requirements (Test Descriptions)
- [x] `HasScopesInterface::setOverride parameter is named $signature`
- [x] `HasScopesInterface::override parameter is named $signature`
- [x] `HasScopesInterface::hasOverride parameter is named $signature`
- [x] `HasScopesInterface::clearOverride parameter is named $signature`
- [x] `HasScopes trait method signatures match the interface parameter names exactly`
- [x] `all existing HasScopesTraitTest assertions still pass with the renamed parameter`

## Acceptance Criteria
- `grep -rn 'scopeKey' packages/scope/src packages/scope-pgsql/src` returns nothing.
- `grep -rn '\$signature' packages/scope/src/Storage` shows the parameter present on all four interface methods + the trait methods.
- All tests still pass (`composer test`).
- `phpcs`, `php-cs-fixer --dry-run`, `phpstan` clean.

## Implementation Notes
- Renamed `$scopeKey` to `$signature` on all four methods in `HasScopesInterface` and `HasScopes` trait.
- Confirmed zero named-argument call sites using `scopeKey:` in the monorepo — all callers use positional args, so no downstream breakage.
- The local iteration variable `$scopeKey` in `ScopedDataSerializer.php` (a `foreach` key) was intentionally left unchanged — it is not part of the interface parameter vocabulary.
- Added 6 new reflection-based tests to `HasScopesTraitTest.php` to verify parameter names and continued behavioral correctness.
- All 429 tests pass; `phpcs`, `php-cs-fixer --dry-run`, and `phpstan` are clean.
