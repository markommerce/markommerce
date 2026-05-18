# Task 008: Docs Index Page

**Status**: completed
**Depends on**: 002, 003, 004, 005, 006, 007
**Retry count**: 0

## Description
Create `docs/src/content/docs/packages/theme-blank/index.md` — the comprehensive package reference page for `markommerce/theme-blank`. Follow `docs/DOCS-STANDARDS.md` Package page structure. Place the file inside a subdirectory (`packages/theme-blank/`) rather than as a flat file (`packages/theme-blank.md`) so Phase 2–5 per-component pages drop in alongside this index without restructuring. The Astro/Starlight sidebar config edit is *not* part of this task — Starlight is not yet installed in this repo; sidebar wiring is a future concern.

## Context
- Read `docs/DOCS-STANDARDS.md` end-to-end before writing.
- Read the two existing package docs pages for tone and structure: `docs/src/content/docs/packages/frontend.md` and `docs/src/content/docs/packages/frontend-demo.md`.
- The page is the *single source of truth* — every piece of information about `theme-blank` lives here. The slim README (task 009) just points here.
- **Required sections** (in order):
  1. **Frontmatter** — `title: markommerce/theme-blank` and a one-line `description:`.
  2. **Intro paragraph** — one-liner expanded into a short paragraph about what the package provides. No `## Overview` heading.
  3. **Installation** — `composer require markommerce/theme-blank` + `npm install @markommerce/theme-blank`.
  4. **Design tokens** — a section explaining the `--mk-*` namespace, with subsections per category (Colors, Spacing, Typography, Radii, Shadows, Motion, Breakpoints). Each subsection is a table listing token name / default value / purpose. Cover *every* token shipped in tasks 002 + 003.
  5. **CSS layers** — explain the cascade-layer contract: `tokens` (from this package), `base` (from this package), `theme` (default layouts CSS from this package, intended for downstream override). Reference the cascade layer order set by `@markommerce/frontend/css/layers.css`. Show how to override `--mk-*` tokens from a consumer's `theme` layer.
  6. **Page layouts** — list all five layouts, the blocks each exposes, and a short Latte example showing how to `{layout '@theme-blank/layout/1column.latte'}` and fill the `content` block.
  7. **JS API** — document `showToast(message, options)` and `openModal(content, options)` with their full signatures, `ToastOptions` / `ModalOptions` / `ModalHandle` types, and a **clear callout** that real behavior arrives in Phase 4. Show example invocations.
  8. **Extending the theme** — two examples: (a) override `--mk-*` tokens inside `@layer theme` to restyle, (b) reference how downstream packages will register mixins via `addMixin()` from `@markommerce/frontend` once components arrive in Phase 2+.
  9. **Web Vitals (CLS prevention)** — document the architectural rule verbatim from `_plan.md`'s Architecture Notes. Explain the `:not(:defined)` safety net. Explain how the Playwright smoke test (task 007) works and how to add fixtures in Phases 2–5. Include the Playwright browser-install command consumers' CI needs.
- **Formatting rules** (from `docs/DOCS-STANDARDS.md`):
  - Add `title="filename"` to file-content code blocks.
  - Root-relative links (`/docs/packages/frontend/`, `/docs/concepts/...`).
  - PHP examples use `use` statements; constructor params follow camelCase-without-`Interface` convention.
  - Em dashes (`---`) for parentheticals.
  - No `## Overview` heading (intro paragraph serves as overview).
- **Do not summarize.** Per DOCS-STANDARDS rule "Transfer all content — if it was worth writing, it's worth keeping." Every token, every layout block, every API signature must appear on the page.
- The path is `docs/src/content/docs/packages/theme-blank/index.md` (note the subdirectory). The existing flat siblings (`frontend.md`, `frontend-demo.md`) stay flat — this is the first package to use the subdirectory pattern; future phases populate it with `mk-button.md`, etc.

## Requirements (Test Descriptions)
- [x] `it ships docs/src/content/docs/packages/theme-blank/index.md`
- [x] `the frontmatter declares title "markommerce/theme-blank" and a non-empty description`
- [x] `the page contains a non-empty intro paragraph immediately after the frontmatter (no heading wrapping it)`
- [x] `the page has an ## Installation section showing both composer require and npm install commands`
- [x] `the page has a ## Design Tokens section with subsections for Colors, Spacing, Typography, Radii, Shadows, Motion, and Breakpoints`
- [x] `every --mk-* token shipped in tasks 002 and 003 appears in at least one tokens table on the docs page` (asserted by reading tokens.css and grepping the docs page)
- [x] `the page has a ## CSS Layers section explaining the cascade-layer contract`
- [x] `the page has a ## Page Layouts section listing base, empty, 1column, 2columns-left, 2columns-right, 3columns and the blocks each exposes`
- [x] `the page has a ## JS API section documenting showToast, openModal, and the option/handle types, with a callout that real behavior lands in Phase 4`
- [x] `the page has a ## Extending the Theme section with at least one token-override example and one mixin example`
- [x] `the page has a ## Web Vitals section documenting the CLS-prevention architectural rule and the :not(:defined) safety net`
- [x] `the page references the npx playwright install --with-deps chromium command for CI consumers`
- [x] `the page contains no ## Overview heading (per DOCS-STANDARDS)`
- [x] `all root-relative links in the page resolve to existing files under docs/src/content/docs/`
- [x] `PHP code examples in the page use use statements rather than fully-qualified class names`

## Acceptance Criteria
- All requirements have passing tests
- The page renders cleanly when `npm run docs:build` runs (if applicable — Starlight isn't installed yet, so a markdown-lint pass is the substitute)
- Markdown lint (if a linter is in the repo; otherwise visual review) reports no errors
- Cross-checked against `docs/DOCS-STANDARDS.md` Package page checklist

## Implementation Notes
(Left blank — filled in by programmer during implementation)
