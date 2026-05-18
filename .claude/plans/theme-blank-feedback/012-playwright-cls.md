# Task 012: Playwright CLS Smoke Test for Feedback Components

**Status**: completed
**Depends on**: 003, 004, 005, 006, 008, 009
**Retry count**: 0

## Description
Create the Playwright CLS smoke test for the 6 Phase 4 feedback components. Mirrors the structure of `forms-cls.spec.ts` / `primitives-cls.spec.ts`. Fixture HTML (`tests/Browser/fixtures/feedback-page.html`) inlines tokens.css, base.css, and all 6 component CSS files (so the test has no external network requests). Renders representative markup for all 6 components in their initial (non-controller-driven) state, then asserts CLS === 0 both before and after JS upgrade.

## Context
- Related files:
  - `packages/theme-blank/tests/Browser/fixtures/feedback-page.html` (new — follows the inline-CSS pattern of `forms-page.html`)
  - `packages/theme-blank/tests/Browser/feedback-cls.spec.ts` (new — copy spec structure from `forms-cls.spec.ts`)
- Patterns to follow: `packages/theme-blank/tests/Browser/forms-cls.spec.ts` (use as the template — same `PerformanceObserver` + 500 ms idle + sum non-`hadRecentInput` values + assert === 0)

## Requirements (Test Descriptions)

The Playwright spec uses Pest-style assertions or Playwright's `expect()`; describe each `test(...)` block here:

- [x] `test pre-upgrade CLS is zero for the feedback fixture` (loads `feedback-page.html` without the JS bundle)
- [x] `test post-upgrade CLS is zero for the feedback fixture` (loads `feedback-page.html` and manually defines stub custom elements that mirror each component's connectedCallback side effects — mirrors `forms-cls.spec.ts` pattern: insert ChildPart comment marker, set role/aria for spinner/skeleton, set default `placement="right"` on `mk-drawer`)
- [x] `test the fixture renders 4 mk-alert variants (info, success, warning, danger)` (sanity check on the markup)
- [x] `test the fixture renders 3 mk-toast elements pre-rendered inside an <ol class="mk-toast-region" role="region" aria-live="polite" aria-label="Notifications"> with each <mk-toast> wrapped in its own <li>` (SSR-style toast region case — fixture MUST produce the full ol/li/mk-toast hierarchy)
- [x] `test the fixture renders 3 mk-spinner sizes (sm, base, lg)`
- [x] `test the fixture renders 3 mk-skeleton variants (text, circle, rect)`
- [x] `test the fixture renders a closed mk-modal with the form <mk-modal><dialog>…content…</dialog></mk-modal> (no open attribute, dialog is display:none by default)`
- [x] `test the fixture renders a closed mk-drawer with placement="right" and the form <mk-drawer placement="right"><dialog>…content…</dialog></mk-drawer>`

## Acceptance Criteria
- All requirements pass (Playwright `npm run test:cls` succeeds locally)
- Fixture has zero external network requests (all CSS inlined)
- Spec follows the established pattern (`PerformanceObserver` + 500 ms wait + sum non-`hadRecentInput` entries)
- Existing `primitives-cls.spec.ts` and `forms-cls.spec.ts` still pass (no regression)

## Implementation Notes
- Created `tests/Browser/fixtures/feedback-page.html` with all CSS inlined (tokens, base, mk-alert, mk-toast, mk-spinner, mk-skeleton, mk-modal, mk-drawer). Open Props vars resolved to literal values so no external requests are made.
- Created `tests/Browser/feedback-cls.spec.ts` following the `forms-cls.spec.ts` pattern (PerformanceObserver + 500ms wait + sum non-hadRecentInput entries).
- Post-upgrade stub connectedCallback mirrors: mk-spinner sets role/aria-live/visually-hidden span; mk-skeleton sets aria-hidden; mk-toast sets role/tabindex; mk-drawer sets default placement="right"; mk-alert and mk-modal have no layout side-effects in the fixture.
- All 8 tests pass; all 13 total CLS tests pass (no regressions in primitives-cls or forms-cls).
