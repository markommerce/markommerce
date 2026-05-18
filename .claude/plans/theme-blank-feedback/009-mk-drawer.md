# Task 009: mk-drawer — Native <dialog> Drawer with placement="left|right"

**Status**: completed
**Depends on**: 001, 002
**Retry count**: 0

## Description
Implement `mk-drawer` as a light-DOM wrapper around a server-rendered native `<dialog>` element, positioned off-canvas as a slide-out drawer. Supports `open`, `size="sm|md|lg"`, `dismissible`, `mk-close` event — same contract as `mk-modal`. Adds `placement="left|right"` (default `right`) that controls CSS positioning. Uses Lit's reactive-property pattern (same as `mk-modal`) — NOT a `MutationObserver`. The native `<dialog>` is positioned using `inset-inline-{start|end}: 0; inset-block: 0; margin: 0;` to anchor the drawer to a side, and `inline-size: var(--mk-drawer-width-*)` for width.

## Context
- Related files:
  - `packages/theme-blank/resources/js/components/mk-drawer.ts` (expand stub)
  - `packages/theme-blank/resources/css/components/mk-drawer.css` (expand stub)
  - `packages/theme-blank/resources/js/components/mk-drawer.test.ts` (new)
- Patterns to follow: `mk-modal.ts` (this task's predecessor — copy the open/close/dismissible plumbing using Lit `@property` + `updated()` and adapt for placement). DO NOT use `MutationObserver`.
- happy-dom limitations (same as task 008): stub `showModal`/`close` via `vi.spyOn`; manually dispatch `new Event('close')` on the dialog to verify the close → mk-close → open=false chain.

## Requirements (Test Descriptions)

- [ ] `it registers under the tag name "mk-drawer" with MkDrawerElement extending MkElement`
- [ ] `it declares open, size, placement, dismissible as Lit @property with reflect: true`
- [ ] `it reflects the placement attribute between property and DOM attribute (left|right)`
- [ ] `it defaults to placement="right" when no placement attribute is set` (JS sets the attribute to `"right"` on connect if absent, so getComputedStyle / hasAttribute both confirm — pick this approach for testability)
- [ ] `it calls dialog.showModal() in updated() when the open property transitions to true`
- [ ] `it calls dialog.close() in updated() when the open property transitions to false`
- [ ] `it removes the open attribute when the inner dialog dispatches a "close" event`
- [ ] `it does NOT recursively call dialog.close() during the close-event reflection`
- [ ] `it dispatches a mk-close CustomEvent (bubbles: true, composed: true) on the wrapper when the dialog closes`
- [ ] `it suppresses cancel and backdrop-click dismissal when dismissible is absent and allows them when dismissible is present`
- [ ] `it calls requireInnerControl(this, 'dialog') in connectedCallback and emits a single console.warn when no inner <dialog> is present`
- [ ] `it re-applies CSS positioning when placement changes from right to left while open` (placement attribute change triggers CSS re-evaluation automatically; assert via the wrapper's getAttribute('placement') after setting the property)

## Acceptance Criteria
- All requirements have passing tests
- CSS: `mk-drawer { display: contents; } mk-drawer > dialog { background: var(--mk-drawer-bg); color: var(--mk-drawer-fg); padding: var(--mk-drawer-padding); box-shadow: var(--mk-drawer-shadow); border: none; inline-size: var(--mk-drawer-width-md); block-size: 100vh; max-block-size: 100vh; margin: 0; } mk-drawer[placement="right"] > dialog { inset-block: 0; inset-inline-end: 0; inset-inline-start: auto; } mk-drawer[placement="left"] > dialog { inset-block: 0; inset-inline-start: 0; inset-inline-end: auto; } mk-drawer[size="sm|lg"] > dialog { inline-size: var(--mk-drawer-width-{sm|lg}); }`
- Default placement is `right` — set via JS in `connectedCallback` (`if (!this.hasAttribute('placement')) this.setAttribute('placement', 'right')`); this guarantees the attribute selector matches without relying on a `:not([placement])` fallback.
- If any open/close transition is added, it must be `opacity` only — no `transform`, `scale`, `translate`, `width`, `height`, or layout-affecting property — and respect `prefers-reduced-motion`. (Phase 4 default: no transition.)
- No `MutationObserver`. Lit reactive properties drive everything.
- The existing `registerBase('mk-drawer', MkDrawerElement)` from task 002 remains the single registration; this task extends the existing class — DO NOT add a second `registerBase` call

## Implementation Notes

See `008-mk-modal.md`'s Implementation Notes for the click / cancel / close listener pattern and the `#reflectingClose` guard. Copy that pattern verbatim, plus:

- In `connectedCallback`, before `super.connectedCallback()`, check `if (!this.hasAttribute('placement')) this.setAttribute('placement', 'right')`. This guarantees the CSS attribute selector `mk-drawer[placement="right"]` matches even when consumers omit the attribute, without relying on a `:not([placement])` CSS fallback.
- Declare `placement` as `@property({ converter: stringOrUndefined, reflect: true }) placement?: 'left' | 'right'` (mirror the pattern in `mk-link.ts`).
