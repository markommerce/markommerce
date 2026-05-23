# Task 025: Compiler applies `Layout::$operations`

**Status**: completed
**Depends on**: 024
**Retry count**: 0

## Description
Teach the resolution-phase compiler to apply the operations declared directly inside a `Layout` file (the new `operations:` field), after the `extends:` chain is resolved but before extensions from `layout/extensions/` files are applied. This makes the layout file itself a participant in the same extension vocabulary as third-party extensions.

## Context
- Current order in `ResolutionPhase::resolveLayout()`: (1) walk `extends:` chain, (2) collect matching extensions by handle, (3) apply each extension's operations in priority order.
- New order delivered by this task: (1) walk `extends:` chain, (2) apply the layout's own `Layout::$operations`, (3) collect matching extensions, (4) apply extension operations in priority order.
- Tasks 026/027 will later insert additional steps between (1) and (2). The canonical full order is documented in `_plan.md` under "Resolution order"; this task implements only steps (1)→(4) above.
- "Own operations" are applied as a dedicated pass *before* the extension priority queue is built — **not** as a priority-0 member of the extension group. This avoids tripping `ResolutionPhase::checkConflicts()` against priority-0 extensions that legitimately target the same placement. The earlier "implicit priority 0" wording is informational only; do not insert own ops into the priority-grouped queue.
- The compiler reuses the existing operation-application methods on `ResolutionPhase` (`applyInsertAfter`, `applyMergeProps`, etc.) — do not duplicate operation-application logic.
- Inheritance (`inherits:`) is handled in task 026. This task only covers operations against the layout's own placements plus the `extends:` shell.

## Requirements (Test Descriptions)
- [x] `it applies layout operations after resolving the extends chain`
- [x] `it applies layout operations before applying extension-file operations`
- [x] `it allows a layout to Remove a placement provided by the extended shell`
- [x] `it allows a layout to MergeProps onto a placement provided by extends`
- [x] `it throws DanglingAnchorException when a layout operation targets a nonexistent name`
- [x] `it preserves existing behavior when the operations list is empty`

## Acceptance Criteria
- All requirements have passing tests
- No regressions in existing layout-demo render output
- PHPStan level 8 clean

## Implementation Notes
- Added a single `foreach` loop in `ResolutionPhase::resolve()` between `convertSlots()` and `collectMatchingExtensions()` that iterates over `$layout->operations` and calls the existing `applyOperation()` method for each.
- The `$discoveredLayout->sourceFile` is passed as the source file argument so `DanglingAnchorException` messages identify the layout file rather than an extension file.
- No new methods were needed — the implementation reuses all existing private operation-application methods via the `applyOperation()` dispatcher.
- Requirements 2-6 all passed immediately after the single implementation in requirement 1, confirming the implementation was minimal and correct.
