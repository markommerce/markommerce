# Task 032: Document default / inheritance / dynamic handles

**Status**: completed
**Depends on**: 031
**Retry count**: 0

## Description
Add a new top-level section to `docs/src/content/docs/guides/working-with-layouts.md` titled "Handles" that explains the handle system end-to-end: what a handle is, how the default handle works, how to inherit one handle from another, and how to declare a `HandleProvider`. Use the new demo files from task 031 as worked examples.

## Context
- File: `docs/src/content/docs/guides/working-with-layouts.md`. Insert the new section between "Repeat Slots" and "Extending a Layout".
- Sub-sections required:
  1. **What a handle is** — the key that connects a layout to a request. Match with a Magento analogy in one sentence.
  2. **The default handle** — sitewide layout merged into every other handle. Show the demo's `default.php`.
  3. **Inheriting a handle** — single-parent inheritance with `inherits:`. Show parent → child append behavior and removing parent placements via `Remove`.
  4. **Dynamic handles** — `HandleProvider` interface and `ProvideHandle` declaration. Show the demo's `GalleryVariantHandleProvider` and explain that providers run after `ContextProvider`s, can read context, and must return statically known handle keys.
  5. **Resolution order** — bullet list explaining: extends → inherits → default → own ops → extension-file ops → runtime dynamic-handle merge.
- Also update `docs/src/content/docs/packages/layout.md` (the API reference):
  - Add `HandleProvider` to the Contracts table with the interface signature.
  - Add `ProvideHandle` to the value-objects section.
  - Add `inherits` and `handleProviders` and `operations` to the `Layout` constructor reference.
  - Add the four new exceptions from task 023 to the exception table.
- The `doc-updater` agent in the post-implementation pipeline will only refresh the package README — the guide and API reference updates must be done explicitly in this task.

## Requirements (Test Descriptions)
- [ ] `it adds a Handles section to the guide between Repeat Slots and Extending a Layout`
- [ ] `it documents the default handle with the demo default.php example`
- [ ] `it documents handle inheritance with parent-child example and Remove operation`
- [ ] `it documents the HandleProvider contract and ProvideHandle value object`
- [ ] `it lists the resolution order from extends through dynamic-handle merge`
- [ ] `it updates the layout package API reference to include the new contracts, value objects, and exceptions`

## Acceptance Criteria
- All requirements have passing tests
- The guide builds without errors in the docs site
- Every code example in the new section is a verbatim copy or contiguous excerpt of a file in `packages/layout-demo/`. Trimming unrelated slots/operations for readability is fine; the snippet must still be a contiguous slice with no inline edits.

## Implementation Notes
(Left blank — filled in during implementation)
