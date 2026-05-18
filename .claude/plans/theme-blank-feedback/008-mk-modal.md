# Task 008: mk-modal — Native <dialog> Wrapper with open Attribute Sync

**Status**: completed
**Depends on**: 001, 002
**Retry count**: 0

## Description
Implement `mk-modal` as a light-DOM wrapper around a server-rendered native `<dialog>` element. Supports `open` boolean attribute (Lit reactive property with `reflect: true`) that syncs to `dialog.showModal()` / `dialog.close()` via `updated(changed)`. Supports `size="sm|md|lg"` (CSS attribute selector mapped to `--mk-modal-width-*`). Supports `dismissible` boolean attribute (default off): when set, backdrop click and ESC close the modal; when absent, both are suppressed (`cancel` event default-prevented, backdrop click ignored). Emits a `mk-close` CustomEvent on the wrapper when the dialog closes (whether via ESC, backdrop, or `close()` call).

Uses Lit's reactive-property pattern (`@property({ type: Boolean, reflect: true }) open = false`) plus `override updated(changed)` to drive the inner `<dialog>` — NOT a `MutationObserver`. A simple instance-field `#reflectingClose` boolean is used while the wrapper removes its own `open` attribute in response to the dialog's `close` event, so the resulting Lit reactive update is a no-op.

## Context
- Related files:
  - `packages/theme-blank/resources/js/components/mk-modal.ts` (expand stub)
  - `packages/theme-blank/resources/css/components/mk-modal.css` (expand stub)
  - `packages/theme-blank/resources/js/components/mk-modal.test.ts` (new)
- Patterns to follow:
  - `mk-button.ts` — `@property({ reflect: true })` + `override updated(changed: Map<string, unknown>)` reactive pattern (verified existing pattern across mk-button/mk-grid/mk-cover/mk-sidebar/mk-switcher)
  - `mk-form.ts` — inner-control wiring + `dispatchEvent(new CustomEvent('mk-...', { bubbles, composed, detail }))`
  - `mk-input.ts` — `requireInnerControl` pattern for asserting an expected inner element
- happy-dom limitations:
  - Stub `HTMLDialogElement.prototype.showModal` / `.close` via `vi.spyOn` in tests; for behavior checks, assert that the spies were called.
  - happy-dom does NOT auto-fire a `close` event when `dialog.close()` is called. To verify the `close → mk-close → open=false` chain in tests, manually dispatch `dialog.dispatchEvent(new Event('close'))` after calling `.close()`.

## Requirements (Test Descriptions)

- [ ] `it registers under the tag name "mk-modal" with MkModalElement extending MkElement`
- [ ] `it declares open as a Lit @property with type: Boolean and reflect: true`
- [ ] `it declares size as a Lit @property with reflect: true (sm|md|lg)`
- [ ] `it declares dismissible as a Lit @property with type: Boolean and reflect: true`
- [ ] `it calls dialog.showModal() in updated() when the open property transitions to true`
- [ ] `it calls dialog.close() in updated() when the open property transitions to false`
- [ ] `it removes the open attribute (sets the open property to false) when the inner dialog dispatches a "close" event`
- [ ] `it does NOT recursively call dialog.close() during the close-event reflection (guard prevents the loop)`
- [ ] `it dispatches a mk-close CustomEvent (bubbles: true, composed: true) on the wrapper when the dialog closes`
- [ ] `it suppresses native ESC dismissal by calling event.preventDefault() on the cancel event when dismissible is absent`
- [ ] `it allows native ESC dismissal (does not preventDefault) when dismissible is present`
- [ ] `it ignores a backdrop click (target===dialog) when dismissible is absent`
- [ ] `it closes the dialog on backdrop click (target===dialog) when dismissible is present`
- [ ] `it calls requireInnerControl(this, 'dialog') in connectedCallback and emits a single console.warn when no inner <dialog> is present`

## Acceptance Criteria
- All requirements have passing tests
- CSS: `mk-modal { display: contents; } mk-modal > dialog { background: var(--mk-modal-bg); color: var(--mk-modal-fg); border-radius: var(--mk-modal-radius); padding: var(--mk-modal-padding); box-shadow: var(--mk-modal-shadow); border: none; max-width: 90vw; max-height: 90vh; } mk-modal[size="sm|md|lg"] > dialog { inline-size: var(--mk-modal-width-{sm|md|lg}); } mk-modal > dialog::backdrop { background-color: var(--mk-modal-backdrop-color); }`
- If any open/close transition is added, it must be `opacity` only — no `transform`, `scale`, `translate`, `width`, `height`, or layout-affecting property — and respect `prefers-reduced-motion`. (Phase 4 default: no transition.)
- No `MutationObserver` instance is created. Lit's reactive-property machinery is the single source of attribute-change observation.
- The `#reflectingClose` instance-field guard is set to `true` immediately before the wrapper updates `open = false` from the dialog's `close` event listener, and reset to `false` afterward, so `updated()` short-circuits the `dialog.close()` call (the dialog is already closed).
- The existing `registerBase('mk-modal', MkModalElement)` from task 002 remains the single registration; this task extends the existing class — DO NOT add a second `registerBase` call

## Implementation Notes

**Backdrop click and ESC handling — implementation specifics:**

1. Add a `click` listener on the inner `<dialog>` (added in `connectedCallback`, removed in `disconnectedCallback`). When `event.target === dialog` (the click landed on the dialog's padding area = the visible backdrop region; clicks on `::backdrop` bubble as dialog clicks): if `this.dismissible` is true, call `dialog.close()`; otherwise no-op. Clicks on dialog content (anything inside the dialog) have `event.target !== dialog` and are ignored.

2. Add a `cancel` listener on the inner `<dialog>`. When `this.dismissible` is false/absent, call `event.preventDefault()` (suppresses ESC). When `dismissible` is true, do nothing — native ESC behavior proceeds and triggers the dialog's `close` event normally.

3. Add a `close` listener on the inner `<dialog>` that:
   - Sets `this.#reflectingClose = true`
   - Sets `this.open = false` (this would normally trigger `updated()` → `dialog.close()`, but the guard short-circuits)
   - Awaits `this.updateComplete`
   - Sets `this.#reflectingClose = false`
   - Dispatches `new CustomEvent('mk-close', { bubbles: true, composed: true })` on the wrapper

4. In `updated(changed)`:
   - If `changed.has('open')`:
     - If `this.open === true` and the dialog is not already open: call `dialog.showModal()`
     - If `this.open === false` and `!this.#reflectingClose` and the dialog is open: call `dialog.close()`

5. Content children inside the dialog MUST NOT have `pointer-events: none` or the target===dialog detection fails for clicks landing on those (they bubble as the child's target).
