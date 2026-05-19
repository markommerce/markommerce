# Task 006: Delete `Scope` Value Object

**Status**: pending
**Depends on**: 004, 005
**Retry count**: 0

## Description
The walker and walkAt are now fully on `ScopeSignature`. Delete the old `Scope` class and every remaining reference. The resolver write-path still uses `Scope` at this point (task 008 reworks it), so this task **does not** change `ScopeResolver`; instead it leaves `ScopeResolver`'s `Scope` imports temporarily broken — task 008 picks that up. To keep CI passing during the gap, this task updates `ScopeResolver` mechanically to take `ScopeSignature` everywhere, deferring proper validation wiring to task 008.

Why split this way: 008 needs the validator (task 003), 005 + 004 already work on `ScopeSignature`. Deleting `Scope` between them and 008 lets 008 focus purely on adding validation. If 003 and 008 happen in the same batch, this is fine.

## Context
- File to delete: `packages/scope/src/Scope.php` — and the test file `packages/scope/tests/Unit/ScopeTest.php`.
- Mechanical sweep — `grep -rln 'Markommerce\\Scope\\Scope[^A-Za-z]' packages/scope packages/scope-pgsql` then update each:
  - `ScopeResolver::resolvedAt(Entity, prop, Scope $scope)` → `resolvedAt(Entity, prop, ScopeSignature $signature)`. Internally: build whatever data structure walkAt expects from the signature. (Validator wiring is task 008 — for now, just pass the signature through.)
  - `ScopeResolver::setOverride(..., Scope $scope)` → `setOverride(..., ScopeSignature $signature)`. Pass `$signature->toString()` to storage (validation is task 008).
  - `ScopeResolver::clearOverride(..., Scope $scope)` → `clearOverride(..., ScopeSignature $signature)`. Same.
- Update every test fixture that does `new Scope('axis', 'path')` to `ScopeSignature::fromArray(['axis' => 'path'])`. Most are in `packages/scope/tests/Unit/Resolver/ScopeResolverTest.php` (already partly handled in task 005 for the walker tests).
- Verify with `grep -rln 'Markommerce\\Scope\\Scope[^A-Za-z]' packages docs` — zero hits expected after this task.
- Update README + `docs/src/content/docs/packages/scope.md` imports `use Markommerce\Scope\Scope;` → `use Markommerce\Scope\Signature\ScopeSignature;` (the example code changes happen in task 009; this task does the import-line sweep only so docs files compile if tested).
- The `Scope::fromString` parser is dropped; `ScopeSignature::fromString` is the replacement. There are no callers of `Scope::fromString` in production code (verified by search) — it was test-only.

## Requirements (Test Descriptions)
- [ ] `the Scope class file no longer exists in packages/scope/src`
- [ ] `no PHP file under packages/scope or packages/scope-pgsql imports Markommerce\Scope\Scope`
- [ ] `no documentation file under docs imports Markommerce\Scope\Scope in a code block`
- [ ] `ScopeResolver::resolvedAt accepts a ScopeSignature parameter`
- [ ] `ScopeResolver::setOverride accepts a ScopeSignature parameter`
- [ ] `ScopeResolver::clearOverride accepts a ScopeSignature parameter`
- [ ] `existing ScopeResolverTest cases still pass with ScopeSignature::fromArray constructions instead of new Scope(...)`

## Acceptance Criteria
- `packages/scope/src/Scope.php` is deleted from the filesystem.
- `packages/scope/tests/Unit/ScopeTest.php` is deleted.
- `grep -rln 'use Markommerce\\\\Scope\\\\Scope;' packages docs` returns nothing (uses grep escapes).
- `composer test` (excluding `integration-destructive`) is green.
- `phpcs`, `php-cs-fixer --dry-run`, `phpstan` clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
