# Task 006: mk-toast — Toast Element with Role + Duration + Dismissible

**Status**: completed
**Depends on**: 001, 002
**Retry count**: 0

## Description
Implement `mk-toast` as the individual toast custom element. Supports `variant="info|success|warning|danger"` (CSS attribute selector), `duration="<ms>"` (number attribute; default 5000, Infinity/0 disables auto-dismiss), and `dismissible` boolean attribute (renders an injected close button). On `connectedCallback`: sets `role="status"` (not `alert` — see plan's risks section) and `tabindex="0"` if not already set, schedules auto-dismiss timer if duration > 0 and < Infinity, and injects close button if `dismissible`. On `disconnectedCallback`: clears the timer.

NOTE: This task implements the toast ELEMENT only. The queue/region/`showToast()` controller lives in task 007.

## Context
- Related files:
  - `packages/theme-blank/resources/js/components/mk-toast.ts` (expand stub)
  - `packages/theme-blank/resources/css/components/mk-toast.css` (expand stub)
  - `packages/theme-blank/resources/js/components/mk-toast.test.ts` (new)
- Patterns to follow: `mk-alert.ts` (dismissible close button injection), `mk-button.ts` (attribute reflection + property converter)

## Requirements (Test Descriptions)

- [x] `it registers under the tag name "mk-toast" with MkToastElement extending MkElement`
- [x] `it reflects the variant attribute between property and DOM attribute`
- [x] `it reflects the duration attribute as a number property`
- [x] `it sets role="status" on connect when role is not already set`
- [x] `it injects a close button on connect when the dismissible attribute is present`
- [x] `it auto-removes the element after duration milliseconds when duration is a positive finite number` (verified via `vi.useFakeTimers()`)
- [x] `it does not auto-remove the element when duration is 0 or Infinity`
- [x] `it clears the auto-dismiss timer on disconnect`

## Acceptance Criteria
- All requirements have passing tests
- CSS for `mk-toast`: `display: block; background: var(--mk-toast-bg); color: var(--mk-toast-fg); padding: var(--mk-toast-padding); border-radius: var(--mk-toast-radius); box-shadow: var(--mk-toast-shadow); min-width: var(--mk-toast-min-width); max-width: var(--mk-toast-max-width);`
- Per-variant left border color: `mk-toast[variant="info|success|warning|danger"]` adds a 4px left border using `--mk-color-{info|success|warning|danger}`
- Region CSS in same file: `.mk-toast-region { position: fixed; bottom: var(--mk-toast-region-inset); right: var(--mk-toast-region-inset); display: flex; flex-direction: column; gap: var(--mk-toast-region-gap); list-style: none; padding: 0; margin: 0; z-index: 1000; }` (region is plain `<ol>`)
- The existing `registerBase('mk-toast', MkToastElement)` from task 002 remains the single registration; this task extends the existing class — DO NOT add a second `registerBase` call

## Implementation Notes
- `variant` uses a custom `ComplexAttributeConverter` (stringOrUndefined) matching the pattern from `mk-alert.ts` and `mk-badge.ts`
- `duration` uses `@property({ type: Number, reflect: true })` with default 5000
- `connectedCallback` sets `role="status"` and `tabindex="0"` if not already present, injects close button when `dismissible` attribute is set, and starts the auto-dismiss timer when `duration > 0 && isFinite(duration)`
- `disconnectedCallback` clears the timer via `clearTimeout`
- Private field `#dismissTimer` tracks the timer ID
- CSS expanded with all required styles including variant left border colors and `.mk-toast-region` layout
- Pre-existing `mk-drawer.css` stylelint failure (inset-inline shorthand) is unrelated to this task
