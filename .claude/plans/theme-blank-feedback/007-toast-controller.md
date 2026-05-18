# Task 007: Toast Controller — showToast() Real Implementation

**Status**: completed
**Depends on**: 006
**Retry count**: 0

## Description
Implement the toast controller module at `packages/theme-blank/resources/js/toast-controller.ts`. Exports the real `showToast(message, options?)` function that replaces the stub in `index.ts`. Lazily creates a singleton `<ol class="mk-toast-region" role="region" aria-live="polite" aria-label="Notifications">` in `<body>` on first call (reuses any existing one already in the DOM), manages a FIFO queue with max 5 visible toasts (oldest evicted via `oldestLi.remove()` when exceeded), creates a `<mk-toast>` element per call with variant/duration/dismissible attributes, wraps it in `<li>`, appends to region.

The controller maintains NO timer state of its own. Each `<mk-toast>` schedules and clears its own auto-dismiss timer (per task 006's `disconnectedCallback`). Eviction is by `Element.remove()` on the `<li>` wrapper — this triggers `mk-toast.disconnectedCallback`, which clears the timer cleanly.

Also: update `packages/theme-blank/resources/js/index.ts` to delegate the existing `showToast` export to `toast-controller.ts` (removing the `console.warn` stub body). Update `packages/theme-blank/resources/js/index.test.ts` to test real behavior (stub-warn assertion is migrated to a deletion).

## Context
- Related files:
  - `packages/theme-blank/resources/js/toast-controller.ts` (new)
  - `packages/theme-blank/resources/js/toast-controller.test.ts` (new)
  - `packages/theme-blank/resources/js/index.ts` (replace `showToast` body)
  - `packages/theme-blank/resources/js/index.test.ts` (update — remove stub-warn assertions, add delegation assertions)
- Existing public signature (unchanged): `showToast(message: string, options?: ToastOptions): void` — type definition stays in `index.ts`

## Requirements (Test Descriptions)

- [ ] `it lazily creates a single ol.mk-toast-region element appended to document.body on first call`
- [ ] `it reuses the existing ol.mk-toast-region element on subsequent calls instead of creating a new one`
- [ ] `it appends a new <li><mk-toast></mk-toast></li> entry to the region per call`
- [ ] `it sets the variant attribute on the mk-toast when options.variant is provided`
- [ ] `it sets the duration attribute on the mk-toast when options.duration is provided`
- [ ] `it evicts the oldest toast li when more than 5 toasts are visible`
- [ ] `it stops emitting the console.warn stub message from showToast()` (assert `console.warn` is NOT called with the stub string)
- [ ] `it removes the toast li wrapper from the region after the mk-toast auto-dismisses` (verified via `vi.useFakeTimers()` + advancing past duration)
- [ ] `it adopts a pre-existing ol.mk-toast-region present in the DOM (does not create a duplicate, does not move it)` (test pre-populates document.body with an <ol class="mk-toast-region"> before calling showToast)
- [ ] `it sets role="region", aria-live="polite", aria-label="Notifications" on the auto-created region (NOT on an adopted pre-existing one — consumer-controlled markup is left as-is)`

## Acceptance Criteria
- All requirements have passing tests
- Public `showToast` signature in `index.ts` is unchanged (no breaking change for consumers)
- The previous stub-warn tests in `index.test.ts` are deleted (they would now fail) — migration is part of this task
- Adopts a pre-existing `<ol class="mk-toast-region">` in DOM rather than failing or duplicating

## Implementation Notes
(Left blank — filled in by programmer during implementation)
