# Task 026: Handle inheritance resolution

**Status**: complete
**Depends on**: 025
**Retry count**: 0

## Description
Resolve `Layout::$inherits` chains at compile time by flattening the parent handle's resolved tree into the child before the child's operations run. The child can then add placements, add context providers, or use operations to modify anything the parent contributed. Inheritance is single-parent only; cycles are a compile error.

## Context
- `inherits:` differs from `extends:` — `extends:` references a `LayoutDefinition` class (a structural shell with slots like `OneColumnLayout`). `inherits:` references another handle (e.g., `'catalog.product'`), inheriting that handle's already-resolved placements, context providers, and operation outcomes.
- A single layout can declare **both** `extends:` and `inherits:`. Resolution order per handle:
  1. Resolve the layout's own `extends:` chain (structural shell from `LayoutDefinition`).
  2. Resolve the `inherits:` parent handle recursively, parent fully resolved first. The parent's already-resolved tree is the result of steps 1–6 applied to the parent.
  3. Merge the parent's slot entries on top of this layout's shell entries (parent placements appended after shell, then own placements appended after parent).
  4. Merge the parent's context providers (parent providers come first, then this layout's own providers).
  5. Apply this layout's own `Layout::$operations` (delivered in task 025).
  6. Apply matching extension-file operations (priority-sorted, delivered before this task).
- The default-handle merge (task 027) is applied **once, at the end**, against each handle's already-resolved tree — it does *not* recurse through `inherits:` parents (otherwise default placements would double-up via inheritance). See task 027 for the canonical placement of that step.
- Slot merge rule: parent slot entries are appended on top of the shell's entries; child slot entries are appended on top of the parent's. To replace or remove parent placements, the child uses operations (Remove, Replace, etc.) — same vocabulary as extensions.
- Context merge rule: child `Provide` entries are concatenated **after** the parent's. Duplicate tokens between parent and child are a compile error — throw `DuplicateContextTokenException` (defined in task 023).
- Cycle detection: build the chain as `[child, parent, grandparent, …]`. If a handle reappears in its own chain, throw `CircularInheritanceException` with the chain in context.
- Unknown parent handle throws `UnknownParentHandleException`.
- Inheritance is resolved by handle-key string lookup (`computeHandleKey` of the parent), NOT by `LayoutDefinition` class reference.

## Requirements (Test Descriptions)
- [x] `it resolves a single-level inheritance chain by appending parent placements before child placements`
- [x] `it resolves a multi-level inheritance chain in ancestor-first order`
- [x] `it merges parent context providers ahead of child context providers`
- [x] `it allows the child to remove a placement contributed by the parent via Remove operation`
- [x] `it allows the child to wrap a placement contributed by the parent via WrapWith operation`
- [x] `it throws CircularInheritanceException when a chain points back to itself`
- [x] `it throws CircularInheritanceException for a multi-step cycle A → B → C → A`
- [x] `it throws UnknownParentHandleException when inherits references a handle that is not defined`
- [x] `it throws DuplicateContextTokenException when parent and child Provide entries declare the same token`
- [x] `it composes extends and inherits on the same layout: shell then parent then own slots, in order`

## Acceptance Criteria
- All requirements have passing tests
- Compiler artifact still serializes round-trip for inherited handles
- PHPStan level 8 clean

## Implementation Notes
- Added topological sort (`topologicalSort` / `topoVisit`) to `ResolutionPhase` to ensure parent handles are always resolved before children. Cycle detection and unknown-parent checks happen during this sort.
- Added `applyInheritanceWithShellAndOwn` which merges three layers of slots in order: shell (from `extends:` chain), parent handle (from `inherits:`), and own (child's `$layout->slots`). Context merges as shell → parent → own with duplicate token detection.
- Added `resolveExtendsChainShellOnly` which resolves only the `extends:` ancestor chain without including the current layout's own slots — needed to keep the shell-before-parent ordering when both `extends:` and `inherits:` are declared.
- The main `resolve` loop branches on `$layout->inherits !== null` to use the new three-layer merge path vs. the existing two-layer extends-only path.
- PHPStan needed `assert($layout->handle !== null)` inside the foreach since `$routableLayouts` only contains non-null handles but PHPStan doesn't infer this from the filter.
