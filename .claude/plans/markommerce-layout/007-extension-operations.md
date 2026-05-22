# Task 007: Extension Operation Value Objects

**Status**: completed
**Depends on**: 004
**Retry count**: 0

## Description
Create the closed vocabulary of typed extension operations and the `LayoutExtension` container that carries them. An extension file `return`s a `LayoutExtension` targeting a handle; the compiler (task 009) applies its operations to the resolved tree.

## Context
- Operation value objects (all `readonly class`, in `src/Operation/`), each implementing a common `Operation` marker interface:
  - `InsertBefore(string $anchorName, Place $placement)`
  - `InsertAfter(string $anchorName, Place $placement)`
  - `Append(string $slotPath, Place $placement)` — append to a named slot; `slotPath` is a dot-path like `content` or `catalog.product_grid:badges`
  - `Prepend(string $slotPath, Place $placement)`
  - `Remove(string $name)`
  - `Replace(string $name, Place $placement)` — full swap of the named placement
  - `MergeProps(string $name, array $props)` — merge into existing props (new keys added, existing keys overwritten)
  - `ReplaceProps(string $name, array $props)` — replace the entire prop set
  - `WrapWith(string $name, string $decorator)` — `decorator` is a `class-string` implementing `DecoratorInterface`
- `LayoutExtension(array|string $handle, array $operations, int $priority = 0)` — `handle` matches the targeted `Layout`'s handle; `operations` is `list<Operation>`; higher `priority` applies later (wins).
- Decide and document the `slotPath` grammar for `Append`/`Prepend` (how a nested slot is addressed). Document in Implementation Notes.
- This task creates only the value objects — application logic is task 009.
- Patterns to follow: `code-standards.md`, task 004 value-object style.

## Requirements (Test Descriptions)
- [x] `it builds an InsertBefore operation with an anchor name and placement`
- [x] `it builds an InsertAfter operation with an anchor name and placement`
- [x] `it builds an Append operation with a slot path and placement`
- [x] `it builds a Prepend operation with a slot path and placement`
- [x] `it builds a Remove operation with a placement name`
- [x] `it builds a Replace operation with a name and replacement placement`
- [x] `it builds a MergeProps operation with a name and prop map`
- [x] `it builds a ReplaceProps operation with a name and prop map`
- [x] `it builds a WrapWith operation with a name and decorator class`
- [x] `it marks every operation with the common operation interface`
- [x] `it builds a LayoutExtension with a handle, operations and a default priority of zero`
- [x] `it builds a LayoutExtension with an explicit priority`

## Acceptance Criteria
- All requirements have passing tests
- All operations and `LayoutExtension` are `readonly class`, none `final`
- Every operation implements the common `Operation` marker interface
- `slotPath` grammar documented in Implementation Notes

## Implementation Notes

### slotPath Grammar

The `slotPath` parameter used by `Append` and `Prepend` is a dot-separated path addressing a named slot in the layout tree:

- `content` — the top-level `content` slot on the root layout
- `content.product_grid` — the `product_grid` sub-slot nested within the `content` slot

Each segment is the key name of a slot in the `slots` array of the parent `Place` or `Layout`. The compiler (task 009) walks this path left-to-right to locate the target slot, then appends or prepends the given `Place` to that slot's list.

### Files Created

- `packages/layout/src/Contracts/Operation.php` — marker interface
- `packages/layout/src/Operation/InsertBefore.php`
- `packages/layout/src/Operation/InsertAfter.php`
- `packages/layout/src/Operation/Append.php`
- `packages/layout/src/Operation/Prepend.php`
- `packages/layout/src/Operation/Remove.php`
- `packages/layout/src/Operation/Replace.php`
- `packages/layout/src/Operation/MergeProps.php`
- `packages/layout/src/Operation/ReplaceProps.php`
- `packages/layout/src/Operation/WrapWith.php`
- `packages/layout/src/LayoutExtension.php`
- `packages/layout/tests/Unit/Operation/ExtensionOperationsTest.php`
