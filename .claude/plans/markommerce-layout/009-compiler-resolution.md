# Task 009: Compiler — Resolution Phase

**Status**: completed
**Depends on**: 005, 007, 008
**Retry count**: 0

## Description
Build the resolution phase of the compiler: take discovered `Layout` and `LayoutExtension` instances, resolve each layout's `extends` chain into a merged tree, then apply matching extensions in priority order to produce one resolved placement tree per handle. No type-checking yet — that is task 010.

## Context
- Input: the discovery result from task 008 (`list<Layout>` + `list<LayoutExtension>`, each path-tagged).
- **Extends resolution**: `Layout::$extends` is a `class-string<LayoutDefinition>`. Call its static `define(): Layout` to get the parent. The parent's slot set acts as the slot vocabulary; the child contributes placements into those slots. Child slots are additive — a child may add placements to a parent slot but referencing a slot the parent never declared is a loud error (this becomes part of task 010's validation; resolution just merges). Resolve transitively (parent may itself `extends`).
- **Extension application**: group extensions by handle, sort by `priority` ascending (lower applied first, higher applied last → higher wins). Within the same priority, apply in a deterministic order (e.g. source file path sort) BUT if two same-priority extensions target the same anchor with conflicting operations (e.g. both `Remove` the same name, or `Replace` + `Remove`), throw `ExtensionConflictException`.
- Apply each operation against the working tree:
  - `InsertBefore`/`InsertAfter` — locate the anchor placement, splice the new placement adjacent to it.
  - `Append`/`Prepend` — locate the slot by `slotPath`, add at end/start.
  - `Remove` — delete the named placement.
  - `Replace` — swap the named placement.
  - `MergeProps`/`ReplaceProps` — modify the named placement's props.
  - `WrapWith` — mark the named placement as wrapped by the decorator (the renderer applies the wrap; resolution records it).
- A `Remove` followed by an `InsertAfter` referencing the removed name (in a later-applied extension) is a `DanglingAnchorException` — but anchor-existence is checked in task 010; here, just apply operations in order and let task 010 validate the final tree. EXCEPTION: if an operation cannot locate its target *during application* (e.g. `Remove` of a non-existent name), throw `DanglingAnchorException` immediately — that is a resolution-time failure, not a final-tree check.
- Output: `array<handleKey, ResolvedLayout>` where `ResolvedLayout` is an intermediate tree (placements with Sources still as `Source` objects, decorators recorded as wrap markers).
- Decide the `handleKey` string format (e.g. `ControllerFQCN::action`) and document it — task 011/016 reuse it.
- **Handle-less base layouts**: a `Layout` with a `null` handle (task 004) is a base layout reachable only via `extends:`. It must NOT appear as a key in the output map (it is not routable). Resolution must (a) resolve such a layout when a child's `extends:` chain reaches it, and (b) skip it entirely when iterating discovered layouts to produce routable handles. A handle-less layout that is never referenced via `extends:` is dead config — emit it as a loud error or a documented warning; decide and document. The `extends:` target resolved via `LayoutDefinition::define()` is also typically handle-less — same rule applies.
- **Template inheritance**: when a routable child `extends:` a base layout, the resolved tree must carry the effective `template` (the child's `template` if set, else the nearest ancestor's). Record it on `ResolvedLayout` so task 011/013 can render the right Latte file.
- Patterns to follow: `marko/layout/src/LayoutProcessor.php` (slot graph assembly), task 008 discovery output.

## Requirements (Test Descriptions)
- [ ] `it resolves a layout with no extends into a single tree`
- [ ] `it merges a layout into its parent via the extends chain`
- [ ] `it resolves a transitive extends chain across three layouts`
- [ ] `it applies an InsertAfter operation placing a new placement next to its anchor`
- [ ] `it applies a Remove operation deleting the named placement`
- [ ] `it applies a Replace operation swapping the named placement`
- [ ] `it applies a MergeProps operation adding new props while keeping existing ones`
- [ ] `it applies a ReplaceProps operation discarding existing props`
- [ ] `it records a WrapWith operation as a decorator marker on the placement`
- [ ] `it applies lower-priority extensions before higher-priority ones`
- [ ] `it throws ExtensionConflictException when two same-priority extensions conflict on one anchor`
- [ ] `it throws DanglingAnchorException when an operation targets a name that does not exist`
- [ ] `it produces one resolved tree per handle key`
- [ ] `it resolves a handle-less base layout reached via an extends chain`
- [ ] `it excludes a handle-less base layout from the routable handle map`
- [ ] `it carries the effective template through the extends chain`

## Acceptance Criteria
- All requirements have passing tests
- Resolution is deterministic — same inputs always produce the same tree
- Conflicts at equal priority fail loud, never silent last-wins
- `handleKey` format documented in Implementation Notes
- Handle-less base layouts are resolved via `extends:` but never keyed as routable handles
- Effective `template` is carried through the `extends:` chain onto `ResolvedLayout`

## Implementation Notes
(Left blank - filled in by programmer during implementation)
