# Task 027: Default handle compile-time merge

**Status**: complete
**Depends on**: 026
**Retry count**: 0

## Description
Recognize the literal handle string `'default'` as a magic compile-time wildcard. The compiler merges a `default` layout's placements, context providers, and operations into **every other handle's resolved tree**. No `default` entry appears in the runtime artifact — it is fully absorbed into siblings.

## Context
- A module declares a default layout the same way as any other: `Layout(handle: 'default', slots: […], operations: […])`. Same value object, no new types.
- **Canonical placement in the resolution pipeline**: default merge runs in two halves to keep "sibling can Remove a default placement" working while also avoiding double-merge through `inherits:`:
  - **Half A (during per-handle resolution, after extends + inherits, before own ops)**: prepend `default`'s slot placements **and context providers** into the sibling tree so the sibling's own operations can `Remove`/`Replace`/`MergeProps` against them. To prevent double-merge through inheritance, the `inherits:` lookup in task 026 reads the **pre-default** resolved tree of the parent handle (i.e., the result of steps 1–6 in task 026, NOT including the default merge). Concretely, the compiler maintains a separate `resolvedLayoutsWithoutDefault` map that inherits consults, and applies default into the canonical map only.
  - **Half B (after all per-handle resolution + extension-file ops, before artifact emission)**: apply `default`'s **operations** (and any `LayoutExtension` files targeting `'default'`) across each sibling tree. Strip the `'default'` entry from the resolved-layouts map.
- Net effect: default placements are visible to sibling own-ops (Half A), default operations and default-targeted extension operations execute last (Half B), no double-merge via inheritance, no `'default'` key in the artifact.
- `Compiler::compile()` runs Discovery → Resolution (Half A inside) → Validation → DefaultOps (Half B) → ArtifactEmit.
- Slot merge rule: default placements are **prepended** to each sibling's matching slot, matching the user mental model that the default handle is the outermost shell.
- Context provider merge rule: default providers are prepended (run first per request). Duplicate tokens between default and sibling are a compile error — throw `DuplicateContextTokenException` (defined in task 023).
- Default cannot itself declare `extends:`, `inherits:`, or `handleProviders:` — each conflicts with the merge semantics. Throw `DefaultHandleConflictException` at compile time if attempted. (Forbidding `handleProviders` on default is a v1 simplification; if every-page dynamic handles are needed later, lift the restriction in a follow-up.)
- `LayoutExtension` files with `handle: 'default'` are merged the same way: their operations apply to every handle in Half B. Priority ordering within `default` extensions still respects the existing rule.
- The runtime artifact `array<handleKey, PreparedTree>` shape stays unchanged. There is no `'default'` key in the final artifact — every handle's tree already contains the merged contents.

## Requirements (Test Descriptions)
- [x] `it merges default-handle placements into every other handle at compile time`
- [x] `it prepends default placements to existing slot entries`
- [x] `it applies default operations across every other handle in the final pass`
- [x] `it allows a sibling's own operations to Remove a placement contributed by default (Half A merges placements first)`
- [x] `it applies default operations only once even when a handle inherits from another handle (no double-merge via inherits)`
- [x] `it omits the default key from the final runtime artifact`
- [x] `it throws DefaultHandleConflictException when default declares extends`
- [x] `it throws DefaultHandleConflictException when default declares inherits`
- [x] `it throws DefaultHandleConflictException when default declares handleProviders`
- [x] `it throws DuplicateContextTokenException when default and a sibling declare the same context token`

## Acceptance Criteria
- All requirements have passing tests
- Middleware code is unchanged — runtime still does a single lookup
- PHPStan level 8 clean

## Implementation Notes
All changes are in `packages/layout/src/Compiler/ResolutionPhase.php`:

- Added `HANDLE_DEFAULT = 'default'` constant.
- Added `validateDefaultHandle()` private method that throws `DefaultHandleConflictException` if the default layout declares `extends`, `inherits`, or `handleProviders`.
- Added `prependDefaultSlots()` private method that merges default's slot placements before each sibling's existing entries.
- Added `mergeDefaultContext()` private method that prepends default's context providers, throwing `DuplicateContextTokenException` on token overlap.
- Modified `resolve()` to implement the two-half approach:
  - **Half A**: Before own operations, prepend default slots/context into each non-default handle. The `inherits` lookup reads `$resolvedLayoutsWithoutDefault` (pre-default-merge snapshot) to avoid double-merge.
  - **Half B**: After all per-handle resolution, apply default's own operations and default-targeted `LayoutExtension` operations to every non-default handle, then 'default' never enters the result map.
- The 'default' key is never added to `$result`, so it's naturally absent from the final artifact.
