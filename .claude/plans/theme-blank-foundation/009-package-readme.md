# Task 009: Slim Package README

**Status**: completed
**Depends on**: 008
**Retry count**: 0

## Description
Create `packages/theme-blank/README.md` following the slim Package README format from `docs/DOCS-STANDARDS.md`. The README is intentionally minimal — title, one-liner, install commands, a single quick example, and a link to the full docs page (task 008). The docs site is the single source of truth; the README is a pointer.

## Context
- Format reference: the "Package README Format" section of `docs/DOCS-STANDARDS.md`.
- Sibling examples to mirror: `packages/frontend/README.md` and `packages/frontend-demo/README.md`.
- **Required structure** (in order):
  1. `# markommerce/theme-blank` (h1, exact Composer name)
  2. One-sentence description, blank line, then 1–2 short sentences expanding on what the package provides
  3. `## Installation` with both `composer require` and `npm install` blocks
  4. `## Quick Example` — one small code snippet conveying the core idea. Suggestion: a Latte snippet using `1column.latte` plus a CSS override snippet showing how to rebind `--mk-color-primary` from a consumer's `@layer theme`. Keep it under ~20 lines total.
  5. `## Documentation` linking to `https://markommerce.dev/docs/packages/theme-blank/`
- Last task of the plan; verify everything shipped in prior tasks is accurately reflected in the README's one-liner and quick example. If anything in Phase 1 changed scope during implementation, fix the docs page (task 008) first, then mirror the change here.

## Requirements (Test Descriptions)
- [ ] `it ships packages/theme-blank/README.md`
- [ ] `the README starts with "# markommerce/theme-blank" as the only h1`
- [ ] `the README has a one-line description immediately after the title`
- [ ] `the README has an ## Installation section with both composer require markommerce/theme-blank and npm install @markommerce/theme-blank commands`
- [ ] `the README has a ## Quick Example section with at least one code block`
- [ ] `the README has a ## Documentation section linking to https://markommerce.dev/docs/packages/theme-blank/`
- [ ] `the README is under 60 lines (slim-format guard)`
- [ ] `the README does not duplicate the full content of the docs page (no token tables, no full API reference)`

## Acceptance Criteria
- All requirements have passing tests
- README renders cleanly on GitHub (visual review)
- Cross-checked against the slim README format in `docs/DOCS-STANDARDS.md`
- Final state of the plan: `_plan.md` status updated to `completed` after this task's PR is merged

## Implementation Notes
(Left blank — filled in by programmer during implementation)
