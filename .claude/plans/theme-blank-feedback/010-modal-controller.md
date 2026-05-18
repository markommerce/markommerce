# Task 010: Modal Controller — openModal() Real Implementation

**Status**: completed
**Depends on**: 008
**Retry count**: 0

## Description
Implement the modal controller module at `packages/theme-blank/resources/js/modal-controller.ts`. Exports the real `openModal(content, options?)` function that replaces the stub in `index.ts`. Builds the DOM in a precise order to avoid `requireInnerControl` warnings (the wrapper's `connectedCallback` runs the moment it is appended to `<body>` — the inner `<dialog>` must already be a child by then):

1. Create wrapper via `document.createElement('mk-modal')`.
2. Create inner `<dialog>` via `document.createElement('dialog')`.
3. Place content into the dialog: HTML string → `dialog.innerHTML = content`; HTMLElement → `dialog.appendChild(content)`.
4. `wrapper.appendChild(dialog)`.
5. Set `size` attribute on the wrapper when `options.size` is provided.
6. Set `dismissible` attribute (no value, presence-only) on the wrapper when `options.dismissible === true`.
7. `document.body.appendChild(wrapper)` — `connectedCallback` runs here and finds the dialog.
8. Set the `open` property/attribute on the wrapper to trigger `showModal()` via Lit's `updated()` hook.

Returns a `ModalHandle` whose `close()` sets `wrapper.open = false` (or removes the attribute); the wrapper's existing close-event handling fires `mk-close`, on which the controller calls `wrapper.remove()`. `close()` is idempotent: a second call is a no-op (already closed → already removed → `wrapper.isConnected === false`).

Also: update `packages/theme-blank/resources/js/index.ts` to delegate the existing `openModal` export to `modal-controller.ts` (removing the `console.warn` stub body). Update `packages/theme-blank/resources/js/index.test.ts` to test real delegation (remove stub-warn assertions).

happy-dom limitation: tests must manually dispatch `dialog.dispatchEvent(new Event('close'))` after `dialog.close()` to fire the `mk-close` → `wrapper.remove()` chain, because happy-dom does not auto-fire `close` on `dialog.close()`.

## Context
- Related files:
  - `packages/theme-blank/resources/js/modal-controller.ts` (new)
  - `packages/theme-blank/resources/js/modal-controller.test.ts` (new)
  - `packages/theme-blank/resources/js/index.ts` (replace `openModal` body)
  - `packages/theme-blank/resources/js/index.test.ts` (remove stub-warn assertions, add delegation test)
- Existing public signature (unchanged): `openModal(content: HTMLElement | string, options?: ModalOptions): ModalHandle`
- happy-dom: spy `HTMLDialogElement.prototype.showModal` / `.close` (same as task 008)

## Requirements (Test Descriptions)

- [ ] `it creates and appends a fresh <mk-modal> element to document.body`
- [ ] `it sets the size attribute on the modal when options.size is provided`
- [ ] `it sets the dismissible attribute on the modal when options.dismissible is true`
- [ ] `it does not set the dismissible attribute when options.dismissible is false or absent`
- [ ] `it appends an HTMLElement content argument to the inner dialog via appendChild`
- [ ] `it assigns a string content argument to the inner dialog via innerHTML`
- [ ] `it sets the open attribute on the wrapper to trigger the dialog showModal call`
- [ ] `it returns a ModalHandle whose close() method removes the open attribute from the wrapper`
- [ ] `it removes the entire <mk-modal> element from the DOM when the mk-close event fires`
- [ ] `it stops emitting the console.warn stub message from openModal()`

## Acceptance Criteria
- All requirements have passing tests
- Public `openModal` signature in `index.ts` is unchanged
- Previous stub-warn tests in `index.test.ts` for `openModal` are deleted
- `ModalHandle.close()` is idempotent (calling twice does not throw)

## Implementation Notes
(Left blank — filled in by programmer during implementation)
