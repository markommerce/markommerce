# Task 014: Docs Pages + Index Update + README

**Status**: completed
**Depends on**: 003, 004, 005, 006, 007, 008, 009, 010, 011
**Retry count**: 0

## Description
Create the 6 per-component docs pages and update the package docs index + theme-blank README. The 6 new docs pages each follow the established structure (`title` / `description` frontmatter, Installation, Usage, API Reference, CSS states, Related Packages). Also: update `docs/src/content/docs/packages/theme-blank/index.md` to:
1. Add a `### Feedback` section listing all 6 components and linking to their docs pages
2. **Remove** the `:::caution[Phase 4 stubs]` block from the JS API section
3. Replace the stub documentation of `showToast()` and `openModal()` with the real behavior (no-stub language)
4. Document the new `openDrawer()` function with `DrawerOptions`, `DrawerHandle`, `DrawerPlacement`
5. Update the `:not(:defined)` Safety Net snippet to include all 28 tags (22 existing + 6 new)
6. Update the "ships … 22 light-DOM custom-element primitives" wording to 28

Update `packages/theme-blank/README.md` similarly if applicable (count refs, feature bullets).

## Context
- Related files:
  - `docs/src/content/docs/packages/theme-blank/mk-alert.md` (new)
  - `docs/src/content/docs/packages/theme-blank/mk-toast.md` (new)
  - `docs/src/content/docs/packages/theme-blank/mk-spinner.md` (new)
  - `docs/src/content/docs/packages/theme-blank/mk-skeleton.md` (new)
  - `docs/src/content/docs/packages/theme-blank/mk-modal.md` (new)
  - `docs/src/content/docs/packages/theme-blank/mk-drawer.md` (new)
  - `docs/src/content/docs/packages/theme-blank/index.md` (extend)
  - `packages/theme-blank/README.md` (update count refs)
- Patterns to follow: `docs/src/content/docs/packages/theme-blank/mk-button.md` (general structure), `mk-form.md` (orchestrator pattern for modal/drawer/toast), `mk-field.md` (component with non-trivial JS API)

## Requirements (Test Descriptions)

- [ ] `it ships a docs page for mk-alert with Installation, Usage, Variants, Dismissible, API Reference sections`
- [ ] `it ships a docs page for mk-toast with Installation, Usage, showToast(), Variants, Accessibility, API Reference sections`
- [ ] `it documents in mk-toast.md the role decision: role="status" for ALL variants (including danger); aria-live="polite" on the region; rationale that role="alert" on every danger toast would cause screen-reader spam; consumers can override role server-side for genuinely critical interruptions`
- [ ] `it ships a docs page for mk-spinner with Installation, Usage, Sizes, Accessibility, API Reference sections`
- [ ] `it ships a docs page for mk-skeleton with Installation, Usage, Variants, Accessibility, API Reference sections`
- [ ] `it ships a docs page for mk-modal with Installation, Usage, openModal(), Native <dialog> behavior, API Reference sections`
- [ ] `it ships a docs page for mk-drawer with Installation, Usage, openDrawer(), Placement, API Reference sections`
- [ ] `it updates the docs index to remove the Phase 4 stubs caution block`
- [ ] `it updates the docs index to add a Feedback section linking all 6 components`
- [ ] `it documents the new openDrawer() function in the JS API section`
- [ ] `it updates the :not(:defined) safety-net snippet in the docs index to cover all 28 tag names`

## Acceptance Criteria
- All requirements have passing tests (a Vitest assertion or a doc-content presence check works; alternatively a static lint that scans for required headings)
- The docs site builds without errors (`npm run build` in `docs/`)
- All cross-references use valid relative URLs
- No mention of "Phase 4 stub" or "stub — real implementation lands in Phase 4" remains in the docs

## Implementation Notes
(Left blank — filled in by programmer during implementation)
