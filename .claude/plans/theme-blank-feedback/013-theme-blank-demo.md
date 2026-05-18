# Task 013: Extend theme-blank-demo with Feedback Section

**Status**: completed
**Depends on**: 003, 004, 005, 006, 007, 008, 009, 010, 011
**Retry count**: 0

## Description
Extend the live demo at `/markommerce/_demo/theme-blank` to showcase all 6 Phase 4 feedback components. Add a `<section class="demo-feedback">` block to `packages/theme-blank-demo/resources/views/showcase.latte`, after the existing Form Controls section. Extend the existing PHP feature test (`packages/theme-blank-demo/tests/Feature/ThemeBlankDemoControllerTest.php`) with a new rendered-HTML assertion that mirrors the existing Phase 2 / Phase 3 precedent (the test that asserts "the rendered page contains every Phase 3 form control tag" using string-contains on the rendered Latte output).

NOTE: The JS-side `packages/theme-blank-demo/resources/js/package.test.ts` exclusively tests Vite/TypeScript build wiring — it has NO rendered-HTML selector tests. The actual e2e/HTML selector assertions live in the PHP feature test. Do NOT add HTML-content assertions to `package.test.ts`.

The demo section must show:
- 4 alerts (one per variant), one with `dismissible`
- 1 spinner per size (3 spinners total)
- 3 skeletons (one of each variant)
- A button that calls `showToast()` from `@markommerce/theme-blank` to add a toast to the bottom-right region
- A button that calls `openModal()` to open a modal with a "Are you sure?" body and a Close button
- A button that calls `openDrawer()` to open a right drawer with mini-cart placeholder content + close button; second button for left drawer

## Context
- Related files:
  - `packages/theme-blank-demo/resources/views/showcase.latte` (extend with new section)
  - `packages/theme-blank-demo/tests/Feature/ThemeBlankDemoControllerTest.php` (extend with one new `it the rendered page contains every Phase 4 feedback tag…` test that asserts `<mk-alert`, `<mk-toast`, `<mk-spinner`, `<mk-skeleton`, `<mk-modal`, `<mk-drawer` are present in the rendered HTML, AND a "Feedback" heading is present)
- Patterns to follow: existing Phase 2 / Phase 3 rendered-tag-presence tests in `ThemeBlankDemoControllerTest.php` (search for "the rendered page contains every Phase 2 primitive tag" and "every Phase 3 form control tag")

## Requirements (Test Descriptions)

PHP-side (Pest, in `ThemeBlankDemoControllerTest.php`):

- [ ] `it the rendered page contains a Feedback heading`
- [ ] `it the rendered page contains every Phase 4 feedback tag (mk-alert, mk-toast, mk-spinner, mk-skeleton, mk-modal, mk-drawer) — single test asserting each tag's presence`
- [ ] `it the rendered page contains at least 4 mk-alert tags (one per variant)` (count `<mk-alert` occurrences ≥ 4)
- [ ] `it the rendered page contains at least one mk-alert with dismissible attribute`
- [ ] `it the rendered page contains a Show toast button, an Open modal button, an Open right drawer button, and an Open left drawer button` (single test asserting each button's text presence)

## Acceptance Criteria
- All requirements have passing tests
- Existing Layout, Typography, and Form Controls sections still render and pass
- The wire-up script for the demo buttons uses `{syntax off}` blocks in Latte (same pattern as the existing form's `mk-submit` listener); the script wires `document.querySelector('button[data-demo="toast"]')` etc. to call `showToast()` / `openModal()` / `openDrawer()` imported via the existing `import '@markommerce/theme-blank'` side-effect in `main.ts` — if a module-level binding is required, extend `main.ts` to attach a `window.markommerceDemo = { showToast, openModal, openDrawer }` helper OR inline a module script in the Latte file with `import` statements (decide and document during implementation)
- The button wire-up does NOT break the existing `mk-form` `mk-submit` listener (both `{syntax off}` blocks coexist)

## Implementation Notes
(Left blank — filled in by programmer during implementation)
