# Plan: theme-blank Feedback (Phase 4)

## Created
2026-05-18

## Status
completed

## Objective
Ship 6 feedback components for `@markommerce/theme-blank` — `mk-alert`, `mk-toast`, `mk-spinner`, `mk-skeleton`, `mk-modal`, `mk-drawer` — and replace the stub `showToast()` / `openModal()` exports in `index.ts` with real controllers (plus a new `openDrawer()`). Modal and drawer are built on the native `<dialog>` element to inherit the browser's focus trap, ESC handling, top-layer rendering, and `::backdrop`. Toasts use a single bottom-right region with a queue (max 5 visible). All components preserve the package's zero-CLS contract and ship with a Playwright `feedback-cls.spec.ts` smoke test.

## Related Issues
none

## Discovery Notes

**Phase 3 deliverables already on `feature/theme-blank-form-controls`** (the branch this phase forks from):
- 22 light-DOM custom elements total (12 layout/typography + 10 form controls)
- `MkElement` + `registerBase()` + `addMixin()` + `requireInnerControl()` + `defineAllComponents()` registry in `@markommerce/frontend`
- `:not(:defined)` safety net in `base.css` covering all 22 tags
- Playwright CLS infrastructure with two specs: `primitives-cls.spec.ts` (12 tags) and `forms-cls.spec.ts` (10 tags)
- `components/index.ts` side-effect entry imports all 22 components
- `packages/theme-blank/resources/js/index.ts` exports STUB `showToast()` and `openModal()` that `console.warn` "Phase 4 stub"
- `theme-blank-demo` package shows all 22 components via `showcase.latte` at `/markommerce/_demo/theme-blank` — Phase 4 extends it with a Feedback section
- 22 docs pages in `docs/src/content/docs/packages/theme-blank/` + index.md

**Architectural decisions locked during Phase 4 brainstorm:**

1. **Modal/drawer foundation = native `<dialog>` + `showModal()`** — inherits free focus trap, ESC dismissal, `::backdrop`, top-layer rendering, body scroll lock (via the native modal-state algorithm). Browser baseline: Chrome 37+, Safari 15.4+, FF 98+ — same `:user-invalid` / `@container` floor established in Phase 2/3. Document in component docs.
2. **Drawer placements = `right` (default) and `left`** — covers mini-cart (right) and nav drawer (left). `top` and `bottom` deferred to a future phase.
3. **Toast region = single bottom-right** — one auto-injected region container appended to `<body>` on first `showToast()` call. Max 5 visible, oldest evicted FIFO when exceeded. Default duration 5000 ms. `role="alert"` for `variant="danger"`, `role="status"` otherwise. `aria-live="polite"` on the region for stack-aware screen readers.
4. **Alert dismissibility = opt-in attribute, no animation** — default `<mk-alert>` is static. `dismissible` attribute renders a `<button class="mk-alert-close" aria-label="Dismiss">` injected by JS in `connectedCallback` (the only JS responsibility); click handler removes the element from DOM instantly. No fade-out; matches keep-it-simple decision.
5. **Hybrid API: declarative + imperative** — every component has a server-renderable HTML form. `<mk-modal>` and `<mk-drawer>` accept an `open` boolean attribute / property that toggles the inner `<dialog>`'s `showModal()` / `close()`. `<mk-toast>` accepts a `duration` attribute and auto-dismisses. The exports `showToast()`, `openModal()`, `openDrawer()` are thin wrappers that create a temporary element, append it to DOM (toasts → region; modal/drawer → body), open it, and return a handle whose `close()` triggers the natural close path which removes the element when the dialog's `close` event fires (modal/drawer) or when the toast timer expires.
6. **Spinner / skeleton = MkElement classes with CSS-only animation** — pure-CSS animation respects `@media (prefers-reduced-motion: reduce)`. Each still gets a `MkElement` subclass + `registerBase()` entry + Vitest registration test + `:not(:defined)` safety-net entry for consistency with all other 24 components.
7. **Stub-first task ordering (Phase 2/3 precedent)** — Task 002 scaffolds **empty stubs** for all 6 component `.ts` and `.css` files, extends `components/index.ts`, and extends `base.css` `:not(:defined)`. Tasks 004-011 expand stubs rather than create them from scratch, preventing the circular ordering where 002 (which imports `./mk-alert` etc.) would fail to compile until 004-011 complete.
8. **Toast region as ordinary `<ol>` element, NOT a custom element** — the controller manages a singleton `<ol class="mk-toast-region" role="region" aria-live="polite" aria-label="Notifications">` lazily injected on first `showToast()`. Keeps the deliverable at exactly 6 components.
9. **Existing index.ts stub replacement** — the existing `showToast`, `openModal`, `ToastOptions`, `ToastVariant`, `ModalOptions`, `ModalSize`, `ModalHandle` exports get their real bodies. Public signatures stay backward-compatible. New: `openDrawer(content, options?)`, `DrawerOptions`, `DrawerHandle`, `DrawerPlacement`. Stub tests in `index.test.ts` are migrated to real behavior tests.

## Scope

### In Scope

- **New feedback design tokens** in `tokens.css` (declared inside the existing `@layer tokens { :root { ... } }`):
  - Alert (10 tokens + 4 border-color tokens): `--mk-alert-bg-{info|success|warning|danger}` × 4, `--mk-alert-fg-{info|success|warning|danger}` × 4, `--mk-alert-border-color-{info|success|warning|danger}` × 4, `--mk-alert-padding`, `--mk-alert-radius`
  - Toast (9 tokens): `--mk-toast-bg`, `--mk-toast-fg`, `--mk-toast-region-gap`, `--mk-toast-region-inset`, `--mk-toast-shadow`, `--mk-toast-radius`, `--mk-toast-padding`, `--mk-toast-min-width`, `--mk-toast-max-width`
  - Modal (9 tokens): `--mk-modal-bg`, `--mk-modal-fg`, `--mk-modal-radius`, `--mk-modal-padding`, `--mk-modal-shadow`, `--mk-modal-backdrop-color`, `--mk-modal-width-{sm|md|lg}` × 3
  - Drawer (7 tokens): `--mk-drawer-bg`, `--mk-drawer-fg`, `--mk-drawer-width-{sm|md|lg}` × 3, `--mk-drawer-shadow`, `--mk-drawer-padding`
  - Spinner (6 tokens): `--mk-spinner-size-{sm|base|lg}` × 3, `--mk-spinner-thickness`, `--mk-spinner-color`, `--mk-spinner-duration`
  - Skeleton (4 tokens): `--mk-skeleton-bg`, `--mk-skeleton-shimmer-color`, `--mk-skeleton-radius`, `--mk-skeleton-duration`

- **6 Lit element classes** (`MkAlertElement`, `MkToastElement`, `MkSpinnerElement`, `MkSkeletonElement`, `MkModalElement`, `MkDrawerElement`) in `packages/theme-blank/resources/js/components/`, each in its own file, extending `MkElement`.

- **6 component CSS files** in `packages/theme-blank/resources/css/components/`, all inside `@layer components`.

- **Toast controller** (`packages/theme-blank/resources/js/toast-controller.ts`) — internal module exporting `showToast(message, options?)`. Lazily creates singleton region, manages FIFO queue (max 5), creates `<mk-toast>` elements, schedules auto-dismiss.

- **Modal/drawer controllers** (`packages/theme-blank/resources/js/modal-controller.ts`, `drawer-controller.ts`) — internal modules exporting `openModal(content, options?)` and `openDrawer(content, options?)`. Each creates a temp `<mk-modal>`/`<mk-drawer>`, appends to `<body>`, opens, removes on close. Returns `ModalHandle`/`DrawerHandle` with `close()`.

- **Extended `index.ts`** — replace `showToast` and `openModal` stub bodies with delegation to controllers; add `openDrawer`, `DrawerOptions`, `DrawerHandle`, `DrawerPlacement` exports.

- **Extended `components/index.ts`** importing all 6 new modules.

- **Extended `base.css`** `:not(:defined)` safety net covering all 6 new tag names.

- **Per-component Vitest unit tests** for all 6 components (registration + light-DOM preservation + behavior).

- **Per-controller Vitest unit tests** for `showToast`, `openModal`, `openDrawer` (with happy-dom shim for `<dialog>.showModal()`).

- **Playwright CLS fixture + spec** (`feedback-page.html` + `feedback-cls.spec.ts`) asserting CLS === 0 pre- and post-upgrade for all 6 controls. Fixture shows: 4 alerts (one per variant, one dismissible), 3 toasts pre-rendered in a region, 3 spinner sizes, 3 skeleton variants, a modal and drawer in closed state (server-rendered HTML for CLS).

- **6 per-component docs pages** at `docs/src/content/docs/packages/theme-blank/mk-{name}.md`.

- **Updated docs index** with a `## Feedback` section linking to all 6 components, AND updated `## JS API` section showing real `showToast` / `openModal` / `openDrawer` behavior (remove the `:::caution[Phase 4 stubs]` block).

- **Theme-blank-demo update** — extend `showcase.latte` with a "Feedback" section showcasing all 6 components, and extend `theme-blank-demo/resources/js/package.test.ts` with feedback-section selectors (mirrors Phase 3 precedent).

- **theme-blank README update** — bump component count from 22 → 28 in any references; add Feedback bullet to features list if applicable.

### Out of Scope

- `top` / `bottom` drawer placements (deferred)
- Configurable toast positions (`top-right`, etc.) — single bottom-right region only
- Animated alert dismiss / fade-out
- Toast swipe-to-dismiss gestures
- Modal/drawer enter/exit animations beyond what `<dialog>::backdrop` provides natively (a single fade transition is acceptable)
- Confirm / prompt modal helpers (`confirmModal()`, `promptModal()`) — phase 5+
- Custom inert-based focus trap (rely on native `<dialog>` behavior)
- Toast persistence across navigations / SSR-rendered toasts
- Programmatic toast dismissal (returning a handle)
- Stacked-modal management (opening a modal on top of a modal — native top-layer handles z-order but we don't add explicit stack APIs)

## Success Criteria

- [ ] All 6 feedback custom elements register, are styled via CSS-only, and produce zero CLS in the Playwright suite (pre- and post-upgrade)
- [ ] `:not(:defined)` safety-net rule in `base.css` covers all 6 new tag names
- [ ] `<mk-modal>` and `<mk-drawer>` correctly toggle the inner `<dialog>` via the `open` attribute/property in both directions (attribute → `showModal()`/`close()` and `close` event → reflect `open=false`)
- [ ] `showToast()` real implementation: lazily creates singleton region, enqueues toast, auto-dismisses after duration, evicts oldest at max 5
- [ ] `openModal(content, options)` returns a `ModalHandle` whose `close()` closes and removes the modal
- [ ] `openDrawer(content, options)` returns a `DrawerHandle` whose `close()` closes and removes the drawer
- [ ] `mk-alert[dismissible]` renders a close button on connect that removes the element when clicked
- [ ] `mk-spinner` and `mk-skeleton` animations stop when `prefers-reduced-motion: reduce` is active
- [ ] Every component has a Vitest unit test asserting registration + light-DOM preservation
- [ ] Every controller has a Vitest unit test covering the happy path + at least one failure / edge case
- [ ] Every component has a docs page with required sections; docs index lists all 6 and the `Phase 4 stubs` caution block is removed
- [ ] `/markommerce/_demo/theme-blank` renders a "Feedback" section showcasing all 6 components and its e2e selectors pass
- [ ] `npm test` passes (Vitest suite, no coverage regression)
- [ ] `npm run test:cls` passes (Playwright — existing specs AND new `feedback-cls.spec.ts`)
- [ ] `composer test` passes (PHP suite, no regression)
- [ ] Stylelint passes on all new CSS

## Task Overview

| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Add feedback design tokens to `tokens.css` (alert/toast/modal/drawer/spinner/skeleton) | - | completed |
| 002 | Scaffold 6 component stubs (.ts + .css) + extend `components/index.ts` + extend `base.css` `:not(:defined)` safety net | - | completed |
| 003 | `mk-alert` — variant attribute (info/success/warning/danger) + `dismissible` close-button injection | 001, 002 | completed |
| 004 | `mk-spinner` — size attribute (sm/base/lg), pure-CSS spin animation, `prefers-reduced-motion` respect, `role="status"` + visually hidden label | 001, 002 | completed |
| 005 | `mk-skeleton` — variant attribute (text/circle/rect), shimmer animation, `prefers-reduced-motion` respect, `aria-hidden="true"` injection | 001, 002 | completed |
| 006 | `mk-toast` — variant attribute + duration attribute + role injection (status/alert) + `dismissible` close button | 001, 002 | completed |
| 007 | Toast controller — `showToast()` real impl, singleton region, FIFO queue (max 5), auto-dismiss, replace stub in `index.ts` | 006 | completed |
| 008 | `mk-modal` — wraps server-rendered `<dialog>`, Lit `@property open` (reflect) drives `showModal()`/`close()` via `updated()`, `size` attribute, `dismissible` (ESC/backdrop-click closes), emits `mk-close` | 001, 002 | completed |
| 009 | `mk-drawer` — wraps server-rendered `<dialog>`, same Lit `@property open` pattern as mk-modal, `placement="left|right"` (default `right` set in `connectedCallback`), `size` attribute, `dismissible`, emits `mk-close` | 001, 002 | completed |
| 010 | Modal controller — `openModal()` real impl returning `ModalHandle`; remove modal from DOM on `close` event; replace stub in `index.ts` | 008 | completed |
| 011 | Drawer controller — `openDrawer()` real impl returning `DrawerHandle`; remove drawer from DOM on `close` event; new export in `index.ts` | 009 | completed |
| 012 | Playwright CLS fixture + spec covering all 6 feedback components | 003, 004, 005, 006, 008, 009 | completed |
| 013 | Extend `theme-blank-demo` `showcase.latte` with Feedback section + extend `tests/Feature/ThemeBlankDemoControllerTest.php` with rendered-tag-presence Pest tests (mirrors Phase 2/3 precedent — NOT `package.test.ts`, which only tests build wiring) | 003, 004, 005, 006, 007, 008, 009, 010, 011 | completed |
| 014 | 6 docs pages (`mk-alert.md`, `mk-toast.md`, `mk-spinner.md`, `mk-skeleton.md`, `mk-modal.md`, `mk-drawer.md`) + update docs `index.md` (Feedback section, remove Phase 4 stub caution, document real `showToast`/`openModal`/`openDrawer`) + update `theme-blank` package README | 003, 004, 005, 006, 007, 008, 009, 010, 011 | completed |

## Architecture Notes

### CLS-prevention rule (unchanged from Phase 2/3)
Every component renders correctly without JavaScript. JS upgrade must not shift layout. The `:not(:defined)` CSS in `@layer base` and the `@layer components` tag-selector rules supply identical layout pre- and post-upgrade.

For modal/drawer specifically: **server-rendered `<mk-modal><dialog>…</dialog></mk-modal>` is `display: none` by default (the native `<dialog>` is `display: none` unless `open`).** JS does not alter the rendered tree when registering — it only attaches listeners. Opening occurs when `open` is set (either by attribute, property, or via `openModal()` wrapper which appends a freshly-created element). This guarantees CLS = 0 for the initial paint regardless of whether `<dialog>` is registered yet.

For toasts: pre-rendered `<mk-toast>` inside a server-injected `<ol class="mk-toast-region">` is allowed for SSR-style notifications. The controller's `showToast()` works on the same region (creating it lazily if absent). The fixture pre-renders 3 toasts to verify CLS = 0 for the SSR case.

### Component pattern (unchanged)
- Light DOM
- `MkElement` subclass + `@property({ reflect: true })` decorators
- `registerBase('mk-name', Class)` at module bottom
- `import '../../css/components/mk-name.css'` side-effect import
- CSS attribute selectors drive visuals; JS reacts only to events / lifecycle

### Modal / drawer attribute → `<dialog>` API mapping

```
mk-modal[open]    →  inner dialog.showModal()        (via Lit @property + updated())
mk-modal[open=""] →  inner dialog.showModal()        (boolean attribute presence)
remove [open]     →  inner dialog.close()            (via Lit @property + updated())
dialog 'close' ev →  set this.open = false on wrapper, guarded by #reflectingClose
                     so the resulting updated() call short-circuits the close branch
                     (the dialog is already closed); then dispatch mk-close
```

The wrapper declares `open` as `@property({ type: Boolean, reflect: true })`. Lit's reactive update pipeline calls `updated(changed)` whenever `open` changes (either via attribute set or property assignment). NO `MutationObserver` is used.

For dismissible: the inner `<dialog>` accepts native ESC by default. The wrapper adds a `cancel` event listener; when `dismissible` is absent, the handler calls `event.preventDefault()` (ESC suppression). The wrapper also adds a `click` listener on the inner `<dialog>`: when `event.target === dialog` (the click landed on the dialog's padding area, which IS the visible backdrop because clicks on `::backdrop` bubble as dialog clicks), the handler calls `dialog.close()` only when `dismissible` is present — otherwise it's a no-op.

### Modal / drawer content contract

Server-rendered markup MUST take the form `<mk-modal><dialog>…content…</dialog></mk-modal>` (or the `mk-drawer` equivalent). Content MUST live inside the inner `<dialog>` — never as a sibling of the dialog directly under the wrapper. Sibling content would leak into the parent layout because the wrapper is `display: contents` while the dialog is `display: none` (closed state). This contract is documented in the component stubs (task 002), in mk-modal.css / mk-drawer.css comments, and in the docs pages (task 014).

### Toast queue contract

- Region: `<ol class="mk-toast-region" role="region" aria-live="polite" aria-label="Notifications">` appended to `<body>` on first `showToast()` call (idempotent — checks if already present)
- Each toast: `<li><mk-toast variant="..." duration="..." dismissible>{message}</mk-toast></li>`
- Max 5 visible at any time. On 6th call, the first (top) toast is removed (its timer cleared).
- Duration default: 5000 ms. `Infinity` or `0` ⇒ no auto-dismiss (only manual via close button).
- `role` on inner toast: `alert` for danger, `status` otherwise.

### Spinner / skeleton accessibility

- `mk-spinner`: JS sets `role="status"` + `aria-live="polite"` if not already set. Visually hidden label slot for screen readers (`<span class="visually-hidden">Loading…</span>` inside).
- `mk-skeleton`: JS sets `aria-hidden="true"` if not already set. Surrounding container is expected to set its own `aria-busy="true"`.

### CSS structure
```
resources/css/components/
  mk-alert.css       # @layer components
  mk-toast.css       # @layer components (also defines .mk-toast-region)
  mk-spinner.css     # @layer components
  mk-skeleton.css    # @layer components
  mk-modal.css       # @layer components
  mk-drawer.css      # @layer components
```

### Stub-first ordering
Task 002 scaffolds empty `.ts` / `.css` files for all 6 components AND extends the `components/index.ts` import list AND extends the `base.css` `:not(:defined)` safety net AND extends the `index.ts` to STILL export the existing stubs (no removal yet). Real implementations land in tasks 003-011. Stub removal from `index.ts` happens inside tasks 007 (toast), 010 (modal), 011 (drawer-new-export).

**Important:** Task 002 calls `registerBase('mk-X', MkXElement)` for every stub. Tasks 003-009 extend the existing class definition (adding `@property` decorators, `connectedCallback`, `updated`, etc.) and MUST NOT add a second `registerBase` call — the registry throws `RegistryError('Base class already registered for tag: mk-X')` on the second call (verified in `packages/frontend/resources/js/registry.ts`).

### Browser baseline
- `<dialog>` + `showModal()` — Chrome 37+ (2021 in Safari 15.4+, FF 98+). Documented in modal/drawer docs.
- `:user-invalid` / `@container` — Phase 2/3 baseline, unchanged.
- `prefers-reduced-motion` — universal support.

### File naming / namespacing
- Controllers: `packages/theme-blank/resources/js/{toast,modal,drawer}-controller.ts` (sibling to `components/`, not inside `components/`). They are not custom elements.
- Tests: `packages/theme-blank/resources/js/{toast,modal,drawer}-controller.test.ts`

## Risks & Mitigations

- **Risk:** `<dialog>.showModal()` is not implemented in happy-dom (the Vitest test environment used by all unit tests in this package). happy-dom also does not auto-fire a `close` event when `dialog.close()` is called.
  - **Mitigation:** Vitest tests use `vi.spyOn(HTMLDialogElement.prototype, 'showModal').mockImplementation(...)` and similarly for `close()`. To verify the `close → mk-close → open=false` chain, tests manually dispatch `dialog.dispatchEvent(new Event('close'))` after calling `.close()`. Tests that need real `<dialog>` behavior (e.g., focus trap, ESC handling) are written as Playwright tests in `feedback-cls.spec.ts` instead.

- **Risk:** Toast region collides with another element if user has their own `.mk-toast-region` in DOM.
  - **Mitigation:** Controller uses `document.querySelector('ol.mk-toast-region')` and reuses any existing region; consumer can pre-render the region anywhere they want (e.g., inside a portal container) and the controller adopts it. Documented in `mk-toast.md`.

- **Risk:** The Lit `updated()` reflection loop — setting `open = false` from the dialog's `close` event triggers `updated()` again, which would call `dialog.close()` on an already-closed dialog.
  - **Mitigation:** The wrapper holds a `#reflectingClose` boolean instance field. The `close` event listener sets it to `true`, sets `this.open = false`, awaits Lit's `updateComplete`, then resets it to `false`. The `updated()` handler checks `#reflectingClose` before calling `dialog.close()` and short-circuits when true. (No `MutationObserver` is used — Lit's reactive-property pipeline is the single source of attribute observation.)

- **Risk:** `<mk-modal>` / `<mk-drawer>` use `display: contents`, which historically removed elements from the accessibility tree.
  - **Mitigation:** Modern browsers (Chrome 65+, Firefox 37+, Safari 11.1+) preserve the a11y tree role for `display: contents` elements when they have semantic content. We do not assign a role to the wrapper — the inner `<dialog>` carries the role. The browser baseline (Chrome 37+ / Safari 15.4+ / FF 98+ for `showModal()`) is well above the a11y fix versions, so this is safe.

- **Risk:** `openModal()` / `openDrawer()` content string sanitization — passing user-controlled HTML via the string overload is an XSS vector.
  - **Mitigation:** Document explicitly in the docs page that the string overload is interpreted as `innerHTML` and the caller is responsible for sanitizing. Prefer `HTMLElement` overload for user-derived content. (Matches existing stub signature already documented in Phase 1 index.md.)

- **Risk:** Modal/drawer accessibility — focus restoration on close.
  - **Mitigation:** Native `<dialog>.close()` automatically restores focus to the element that had focus when `showModal()` was called. Document this as a built-in behavior; do not re-implement.

- **Risk:** Backdrop click closes the modal even when `dismissible=false`.
  - **Mitigation:** Click handler on inner `<dialog>` checks `event.target === dialog` (clicks on `::backdrop` bubble as dialog clicks). When `dismissible=false`, call `event.preventDefault()` — but `<dialog>` doesn't have a cancelable cancel on backdrop click, so instead the click handler simply does nothing when `dismissible=false`, and the `cancel` event (ESC) is `event.preventDefault()`-ed. Native ESC behavior is suppressable via the `cancel` event.

- **Risk:** Stylelint does not know the new `--mk-{alert,toast,modal,drawer,spinner,skeleton}-*` token names.
  - **Mitigation:** Same as Phase 3 — stylelint's `property-no-unknown` rule ignores all custom properties via `/^--/`. New tokens are accepted automatically.

- **Risk:** Toast region is created lazily and never has stable position pre-paint, potentially shifting other content.
  - **Mitigation:** Region is `position: fixed` (bottom: var(--mk-toast-region-inset); right: var(--mk-toast-region-inset)) so it never participates in layout. CLS impact = 0.

- **Risk:** Skeleton shimmer animation causes paint jank on large lists.
  - **Mitigation:** Use `@property` / GPU-accelerated `transform: translateX(...)` or `background-position` on a fixed gradient (no layout/paint cost on the skeleton itself). Document the technique in `mk-skeleton.md`.

- **Risk:** `mk-toast` `role="alert"` on every danger toast announces interruptively, causing screen-reader spam.
  - **Mitigation:** Region holds `aria-live="polite"`; individual toast `role` is `status` even for `danger` (the original `alert` plan is replaced). For genuinely critical notifications, the consumer can override `role` server-side. Documented in `mk-toast.md`.
