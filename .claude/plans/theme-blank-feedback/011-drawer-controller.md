# Task 011: Drawer Controller — openDrawer() New Export

**Status**: completed
**Depends on**: 009
**Retry count**: 0

## Description
Implement the drawer controller module at `packages/theme-blank/resources/js/drawer-controller.ts`. Adds a new `openDrawer(content, options?)` export to `packages/theme-blank/resources/js/index.ts` (no previous stub existed). Mirrors `openModal()` DOM-construction order precisely (avoids `requireInnerControl` warnings):

1. `const wrapper = document.createElement('mk-drawer')`.
2. `const dialog = document.createElement('dialog')`.
3. Place content: HTML string → `dialog.innerHTML = content`; HTMLElement → `dialog.appendChild(content)`.
4. `wrapper.appendChild(dialog)`.
5. Set `size` on the wrapper when `options.size` is provided.
6. Set `placement` on the wrapper from `options.placement ?? 'right'` (always set, so `connectedCallback`'s default-placement logic is a no-op).
7. Set `dismissible` (presence-only) when `options.dismissible === true`.
8. `document.body.appendChild(wrapper)` — `connectedCallback` runs.
9. Set `open` on the wrapper to trigger `showModal()` via Lit's `updated()`.

Returns `DrawerHandle` whose `close()` sets `wrapper.open = false`; the wrapper's close-event handling fires `mk-close`, on which the controller calls `wrapper.remove()`. `close()` is idempotent.

Also add public type exports: `DrawerOptions`, `DrawerHandle`, `DrawerPlacement` in `index.ts`.

happy-dom limitation: tests must manually dispatch `dialog.dispatchEvent(new Event('close'))` after `dialog.close()` to verify the close → mk-close → wrapper.remove chain.

## Context
- Related files:
  - `packages/theme-blank/resources/js/drawer-controller.ts` (new)
  - `packages/theme-blank/resources/js/drawer-controller.test.ts` (new)
  - `packages/theme-blank/resources/js/index.ts` (add `openDrawer`, `DrawerOptions`, `DrawerHandle`, `DrawerPlacement` exports)
  - `packages/theme-blank/resources/js/index.test.ts` (add `openDrawer` delegation tests)
- Public signature (new): `openDrawer(content: HTMLElement | string, options?: DrawerOptions): DrawerHandle`
- `DrawerOptions`: `{ size?: 'sm' | 'md' | 'lg'; placement?: 'left' | 'right'; dismissible?: boolean }`
- `DrawerHandle`: `{ close(): void }`
- `DrawerPlacement`: `'left' | 'right'`

## Requirements (Test Descriptions)

- [x] `it creates and appends a fresh <mk-drawer> element to document.body`
- [x] `it sets the size attribute on the drawer when options.size is provided`
- [x] `it sets the placement attribute on the drawer when options.placement is provided`
- [x] `it defaults to placement="right" when options.placement is absent`
- [x] `it sets the dismissible attribute when options.dismissible is true`
- [x] `it appends HTMLElement content to the inner dialog and assigns string content via innerHTML`
- [x] `it sets the open attribute on the wrapper to trigger showModal`
- [x] `it returns a DrawerHandle whose close() removes the open attribute`
- [x] `it removes the entire <mk-drawer> from the DOM when the mk-close event fires`
- [x] `it exports DrawerOptions, DrawerHandle, DrawerPlacement type aliases from index.ts`

## Acceptance Criteria
- All requirements have passing tests
- `DrawerHandle.close()` is idempotent
- `openDrawer` is exported alongside `openModal` and `showToast` in `index.ts`

## Implementation Notes
- Created `drawer-controller.ts` with `openDrawer()` following the exact DOM-construction order described
- `DrawerHandle.close()` is idempotent via `wrapper.isConnected` guard
- The `mk-close` event listener is attached after `document.body.appendChild(wrapper)` to avoid race conditions
- Types `DrawerOptions`, `DrawerHandle`, `DrawerPlacement` are exported from `drawer-controller.ts` and re-exported from `index.ts`
- `index.ts` delegates `openDrawer()` to `drawer-controller.ts` (same pattern as `openModal` and `showToast`)
- The linter auto-updated `index.ts` to also wire up `modal-controller.ts` (task 010 had been prepared)
