# Task 017: Docs Index `## Components` Section + Cross-Links

**Status**: completed
**Depends on**: 003, 004, 005, 006, 007, 008, 009, 010, 011, 012, 013, 014
**Retry count**: 0

## Description
Add a `## Components` section to the existing `docs/src/content/docs/packages/theme-blank/index.md` page that lists all 12 Phase 2 primitives with one-line descriptions and root-relative links to each per-component page. Also verify every component docs page from tasks 003-014 follows DOCS-STANDARDS and that the index's links resolve to existing files.

## Context

- File to update:
  - `docs/src/content/docs/packages/theme-blank/index.md` — insert new `## Components` section after the existing `## Page Layouts` section (and before `## JS API`)
- Files to verify (created by tasks 003-014):
  - `docs/src/content/docs/packages/theme-blank/mk-stack.md`
  - `docs/src/content/docs/packages/theme-blank/mk-cluster.md`
  - `docs/src/content/docs/packages/theme-blank/mk-grid.md`
  - `docs/src/content/docs/packages/theme-blank/mk-container.md`
  - `docs/src/content/docs/packages/theme-blank/mk-sidebar.md`
  - `docs/src/content/docs/packages/theme-blank/mk-switcher.md`
  - `docs/src/content/docs/packages/theme-blank/mk-cover.md`
  - `docs/src/content/docs/packages/theme-blank/mk-divider.md`
  - `docs/src/content/docs/packages/theme-blank/mk-heading.md`
  - `docs/src/content/docs/packages/theme-blank/mk-text.md`
  - `docs/src/content/docs/packages/theme-blank/mk-link.md`
  - `docs/src/content/docs/packages/theme-blank/mk-badge.md`

### Section structure

```markdown
## Components

`markommerce/theme-blank` ships a set of light-DOM custom-element primitives. Each primitive is purely presentational --- it wraps your server-rendered HTML with consistent styling, never replacing or restructuring children. All visual behavior is driven by CSS attribute selectors, so every primitive produces zero CLS even before its JavaScript is loaded.

### Layout primitives

| Component | Description |
| --- | --- |
| [mk-stack](/docs/packages/theme-blank/mk-stack/) | Vertical rhythm with consistent gap |
| [mk-cluster](/docs/packages/theme-blank/mk-cluster/) | Horizontal flex-wrap row for button rows, tag lists |
| [mk-grid](/docs/packages/theme-blank/mk-grid/) | Responsive auto-fit grid driven by a minimum column width |
| [mk-container](/docs/packages/theme-blank/mk-container/) | Max-width content container with auto inline margins |
| [mk-sidebar](/docs/packages/theme-blank/mk-sidebar/) | Sidebar + main with implicit-flexbox collapse |
| [mk-switcher](/docs/packages/theme-blank/mk-switcher/) | Row→column switch via flex-basis arithmetic |
| [mk-cover](/docs/packages/theme-blank/mk-cover/) | Header / centered main / footer full-height frame |
| [mk-divider](/docs/packages/theme-blank/mk-divider/) | Styled separator with auto-injected `role="separator"` |

### Typography primitives

| Component | Description |
| --- | --- |
| [mk-heading](/docs/packages/theme-blank/mk-heading/) | Single-tag heading with ARIA role + level (two-tag form supported for SEO-critical content) |
| [mk-text](/docs/packages/theme-blank/mk-text/) | Single-tag body text with body / lead / small / muted variants (two-tag form supported for nested content) |
| [mk-link](/docs/packages/theme-blank/mk-link/) | Wrapper around native `<a>` with variant + underline-policy control |
| [mk-badge](/docs/packages/theme-blank/mk-badge/) | Inline-flex status badge with semantic variants |
```

### Cross-link verification

For each per-component docs page that tasks 003-014 created:
- Frontmatter must include `title: mk-{name}` and a one-line `description:`
- The page has an intro paragraph immediately after the frontmatter (no `## Overview` heading)
- Required sections present (HTML Usage, Attributes, Slots, Events, CSS Custom Properties, Variants & States, Extending, Accessibility)
- All root-relative links resolve to existing files under `docs/src/content/docs/`

This task is the integration checkpoint for the whole Phase 2 documentation surface.

### Phase 2 status update

After the docs cross-link verification passes, set `_plan.md` status from `in_progress` to `completed` (the plan-orchestrate skill handles this transition automatically once the last task lands; this note is informational).

## Requirements (Test Descriptions)
- [ ] `the docs index page contains a "## Components" section`
- [ ] `the Components section has a "Layout primitives" sub-table with rows for all 8 layout primitives`
- [ ] `the Components section has a "Typography primitives" sub-table with rows for all 4 typography primitives`
- [ ] `every component link in the Components section resolves to an existing markdown file under docs/src/content/docs/packages/theme-blank/`
- [ ] `every per-component docs page has a frontmatter with title and non-empty description`
- [ ] `every per-component docs page has an intro paragraph immediately after the frontmatter`
- [ ] `every per-component docs page contains the required sections (HTML Usage, Attributes, Slots, Events, CSS Custom Properties, Variants & States, Extending, Accessibility)`
- [ ] `no per-component docs page contains a ## Overview heading (DOCS-STANDARDS rule)`
- [ ] `the Components section appears between ## Page Layouts and ## JS API in the index page`

## Acceptance Criteria
- All requirements have passing tests
- Markdown lint (if configured) passes on all updated/created docs files
- Visual review confirms the index page reads naturally with the new section
- `npm run docs:build` (if Starlight gets installed mid-phase) succeeds; otherwise markdown structure is verified by the requirement tests above

## Implementation Notes
(Left blank — filled in by programmer during implementation)
