# Task 001: Add Feedback Design Tokens

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add the new `--mk-*` design tokens required by Phase 4 feedback components (alert, toast, modal, drawer, spinner, skeleton) to `packages/theme-blank/resources/css/tokens.css`. All tokens go inside the existing `@layer tokens { :root { … } }` block, grouped by component with a header comment, following the Phase 3 grouping pattern (`/* --- Alert ---------- */`, etc.). Add dark-mode overrides where backgrounds/foregrounds change.

## Context
- Related files:
  - `packages/theme-blank/resources/css/tokens.css` (extend)
  - `packages/theme-blank/resources/js/tokens.test.ts` (EXTEND — existing file; do NOT create a new tokens.test.ts under resources/css)
- Patterns to follow: existing groupings (`/* --- Input / Select / Textarea --- */`, `/* --- Field --- */`); existing assertions in `tokens.test.ts` that read `tokens.css` and grep for `--mk-*` token names inside the `@layer tokens { :root { ... } }` block
- Dark-mode block uses `[data-theme="dark"]` selector inside the same `@layer tokens`

## Requirements (Test Descriptions)

CSS unit assertions added as new `it(...)` blocks inside the existing `packages/theme-blank/resources/js/tokens.test.ts`. Each requirement asserts the presence of all tokens in its group inside the `@layer tokens { :root { ... } }` block. (Token VALUES are not asserted by tests; the assertion is "declared and non-empty.")

- [ ] `it declares all 10 mk-alert tokens inside :root` (`--mk-alert-bg-{info,success,warning,danger}` × 4, `--mk-alert-fg-{info,success,warning,danger}` × 4, `--mk-alert-padding`, `--mk-alert-radius`)
- [ ] `it declares all 4 mk-alert-border-color tokens inside :root` (`--mk-alert-border-color-{info,success,warning,danger}`)
- [ ] `it declares all 9 mk-toast tokens inside :root` (`--mk-toast-bg`, `--mk-toast-fg`, `--mk-toast-region-gap`, `--mk-toast-region-inset`, `--mk-toast-shadow`, `--mk-toast-radius`, `--mk-toast-padding`, `--mk-toast-min-width`, `--mk-toast-max-width`)
- [ ] `it declares all 7 mk-modal tokens inside :root` (`--mk-modal-bg`, `--mk-modal-fg`, `--mk-modal-radius`, `--mk-modal-padding`, `--mk-modal-shadow`, `--mk-modal-backdrop-color`, `--mk-modal-width-{sm,md,lg}` × 3 = 9 total declared as a single conceptual group)
- [ ] `it declares all 5 mk-drawer tokens inside :root` (`--mk-drawer-bg`, `--mk-drawer-fg`, `--mk-drawer-width-{sm,md,lg}` × 3 = 5 total, `--mk-drawer-shadow`, `--mk-drawer-padding`)
- [ ] `it declares all 4 mk-spinner token groups inside :root` (`--mk-spinner-size-{sm,base,lg}` × 3, `--mk-spinner-thickness`, `--mk-spinner-color`, `--mk-spinner-duration`)
- [ ] `it declares all 4 mk-skeleton tokens inside :root` (`--mk-skeleton-bg`, `--mk-skeleton-shimmer-color`, `--mk-skeleton-radius`, `--mk-skeleton-duration`)
- [ ] `it overrides at least 3 feedback tokens inside [data-theme="dark"]` (at minimum `--mk-toast-bg`, `--mk-modal-bg`, `--mk-skeleton-bg` flip to dark surfaces)

## Acceptance Criteria
- All requirements have passing tests
- `npm run lint:css` passes (no new stylelint violations)
- Tokens reference existing `--mk-*` or Open Props variables wherever possible (no raw hex values)
- New tokens documented in plan (Architecture Notes section already lists them)
