# Task 005: walkAt(ScopeSignature) — Single-Axis-Only Enforcement

**Status**: pending
**Depends on**: 004
**Retry count**: 0

## Description
Rework `ScopeWalker::walkAt()` so it accepts a `ScopeSignature` instead of the soon-to-be-deleted `Scope`. Multi-axis signatures are rejected with a clear exception (the spec defers multi-axis `walkAt` past MVP). Single-axis behavior — walk up the hierarchy of the one axis in storage — is preserved exactly.

Covers behavioral cases 14 and 15 from the brief.

## Context
- File: `packages/scope/src/Resolution/ScopeWalker.php` (edits `walkAt` only; do NOT re-touch `walk()`).
- Old signature: `walkAt(HasScopesInterface $overrides, string $property, list<string> $axes, Scope $scope, ScopeRegistryInterface $registry): ScopeWalkResult`.
- New signature: `walkAt(HasScopesInterface $overrides, string $property, list<string> $axes, ScopeSignature $signature, ScopeRegistryInterface $registry): ScopeWalkResult`.
- New exception: `MultiAxisWalkAtNotSupportedException` (extends `MarkoException`, named `message` / `context` / `suggestion`, static factory `forSignature(ScopeSignature $sig)`). Lives at `packages/scope/src/Exceptions/MultiAxisWalkAtNotSupportedException.php`.
- Implementation:
  ```
  if count(signature.axes) !== 1: throw MultiAxisWalkAtNotSupportedException::forSignature(signature)
  axisName = first axis of signature
  if axisName not in attributeAxes: return notFound()
  path = signature.get(axisName)
  walked = registry.getHierarchy(axisName).walkUp(path)
  match = array_find(walked, fn($p) => overrides.hasOverride("$axisName:$p", $property))
  return match !== null ? found(overrides.override("$axisName:$match", $property)) : notFound()
  ```
- Old single-axis sig string format (`"axisName:path"`) is preserved (single-axis signature's `toString()` MUST produce exactly `"axis:value"` — verified in task 001's "round-trips single-axis through fromString and toString" requirement).
- Existing walkAt tests in `packages/scope/tests/Unit/Resolution/ScopeWalkerTest.php` need their `new Scope(...)` calls rewritten to `ScopeSignature::fromArray(['geo' => 'eu.de'])` or similar. KEEP the assertions; only update the construction.

## Requirements (Test Descriptions)
- [ ] `walkAt with a single-axis signature returns the override at that scope (case 14: explicit single-axis still works)`
- [ ] `walkAt with a single-axis signature walks up the hierarchy to find an ancestor match`
- [ ] `walkAt with a single-axis signature returns notFound when the axis is not in the attribute axes`
- [ ] `walkAt with a single-axis signature returns notFound when neither the scope nor any ancestor has an override`
- [ ] `walkAt throws MultiAxisWalkAtNotSupportedException when the signature has two axes (case 15)`
- [ ] `walkAt throws MultiAxisWalkAtNotSupportedException when the signature has three axes`
- [ ] `walkAt ignores the ScopeContext entirely (the signature axes determine the lookup)`

## Acceptance Criteria
- All requirements have passing tests.
- All existing `walkAt` tests in `ScopeWalkerTest.php` updated to construct `ScopeSignature` instead of `Scope` — verified via `grep -n 'new Scope(' packages/scope/tests/Unit/Resolution/ScopeWalkerTest.php` returning zero hits.
- The new exception is documented in the class-level `@throws` PHPDoc on `walkAt`.
- `phpcs`, `php-cs-fixer --dry-run`, `phpstan` clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
