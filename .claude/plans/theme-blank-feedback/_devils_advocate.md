# Devil's Advocate Review: theme-blank-feedback

## Critical (Must fix before building)

### C1. Task 001 references the wrong path for the tokens test file
The task says "create if missing" at `packages/theme-blank/resources/css/tokens.test.ts`, but the existing tokens test file lives at `packages/theme-blank/resources/js/tokens.test.ts` (verified — file exists with the same `tokens.css`-reading pattern). Creating a second test file in the wrong directory would fragment coverage and likely never run under the vitest config (which expects `.test.ts` files inside the workspace's resolved package).

**Fix applied:** Update task 001's referenced path to `packages/theme-blank/resources/js/tokens.test.ts` (extend the existing file, do not create a duplicate).

### C2. Task 001 token list contradicts the _plan.md token list
Plan section "In Scope" lists per-variant tokens:
- `--mk-alert-fg-{info|success|warning|danger}` (4 tokens)
- `--mk-alert-border-color-{info|success|warning|danger}` (4 tokens)
- `--mk-toast-max-width` (1 token)

Task 001's requirements list these as singular `--mk-alert-fg`, `--mk-alert-border-color`, and omit `--mk-toast-max-width` entirely. Task 003 (mk-alert acceptance) requires `--mk-alert-fg-*` / `--mk-alert-border-color-*` per variant; Task 006 (mk-toast acceptance) requires `--mk-toast-max-width`. A worker building 001 from the requirements would produce a token set that fails 003 and 006 tests.

**Fix applied:** Rewrite task 001 requirements to enumerate the correct per-variant token names and include `--mk-toast-max-width`. Update counts in the "it declares all N mk-X tokens" labels.

### C3. Task 004 references a non-existent `visually-hidden` utility class
The task says the spinner renders a slot using "the `visually-hidden` utility class." A grep across the codebase confirms this class is defined nowhere in `theme-blank` (the only mentions are in this plan). Without defining it, the assertion that screen readers announce the label fails; a worker has no source to reference.

**Fix applied:** Add a task 004 requirement to declare `.mk-visually-hidden` (or equivalent) inside `mk-spinner.css` under `@layer components`, with the standard clip-path/absolute-position recipe, and bound to a slot/`<span>` inside `mk-spinner`. Alternatively, drop the slot/label requirement (kept simpler: inject `<span class="mk-visually-hidden">Loading…</span>` in `connectedCallback` when no slot content exists, and define the class locally). The plan now standardizes this approach.

### C4. Task 013 confuses the JS package-wiring test file with the PHP rendered-HTML tests
Task 013 says: "extend `packages/theme-blank-demo/resources/js/package.test.ts` with feedback-section selectors (mirrors Phase 3 precedent)." But `package.test.ts` tests Vite/TypeScript wiring — it has no rendered-HTML assertions. The Phase 3 precedent that asserts "the rendered page contains every Phase 3 form control tag" lives in `packages/theme-blank-demo/tests/Feature/ThemeBlankDemoControllerTest.php` (verified — contains `mk-button`, `mk-input`, etc. string-contains assertions on rendered Latte output).

A worker reading task 013 literally would extend the wrong file and miss the actual Phase 3 precedent.

**Fix applied:** Update task 013 to point to the PHP feature test (`tests/Feature/ThemeBlankDemoControllerTest.php`) for the rendered-tag assertions, and clarify that the e2e/click-behavior tests for the demo buttons (if any) belong in a separate browser/Playwright spec rather than `package.test.ts`. Adjust requirements to match.

### C5. Task 002 default display values for mk-modal / mk-drawer are incomplete
Task 002 says `mk-modal` and `mk-drawer` get `display: contents` in their stubs. The inner `<dialog>` element has its own browser-default `display: none` until `open`, so this looks fine. However, **the stub CSS has no rule keeping the dialog hidden initially before tasks 008/009 land**. If the fixture or demo server-renders `<mk-modal><dialog>...</dialog></mk-modal>` with no `open` attribute, `<dialog>` is correctly `display: none`. But the stub will not yet have a `:not([open])` clause, and any inner content placed inside the wrapper-but-outside-the-dialog (e.g., before the dialog tag) would be visible. The plan needs to mandate that server-rendered content for a closed modal lives inside `<dialog>` — not as siblings of `<dialog>` inside `<mk-modal>`.

**Fix applied:** Clarify in task 002 acceptance criteria (and in `_plan.md` Architecture Notes for modal/drawer) that server-rendered markup for modal/drawer requires the form `<mk-modal><dialog>...content...</dialog></mk-modal>` — content outside the inner `<dialog>` is unsupported and would render unintentionally. Task 008/009 already imply this; making it explicit in 002 prevents the fixture from accidentally placing non-dialog content directly under `<mk-modal>`.

## Important (Should fix before building)

### I1. Task 008 / 009 use `MutationObserver` where Lit's `@property` + `updated()` is idiomatic
Existing components use `@property({ reflect: true })` + `override updated(changed)` to react to attribute/property changes (see `mk-button.ts`, `mk-grid.ts`, `mk-cover.ts`, `mk-sidebar.ts`, `mk-switcher.ts`). The plan introduces a brand-new `MutationObserver` pattern just to watch `open` and `placement` — but Lit already exposes both as reactive properties when declared via `@property`. The `MutationObserver` adds an extra abstraction layer, a re-entrancy guard concern that doesn't exist with `updated()`, and a disconnect-cleanup obligation.

**Fix applied:** Update tasks 008 and 009 to declare `@property({ type: Boolean, reflect: true }) open = false`, `@property() size`, `@property() placement` (and `@property({ type: Boolean, reflect: true }) dismissible`), and react in `updated(changed)`. Remove the `MutationObserver` and `#reflecting` infinite-loop language; replace with a plain "set guard via flag while reflecting `open=false` after the dialog's `close` event so Lit's reactive update doesn't recurse." Update `_plan.md` Architecture Notes "Modal / drawer attribute → <dialog> API mapping" and Risks accordingly.

### I2. Plan's "single fade transition is acceptable" violates the strict CLS-zero contract risk
The Out-of-Scope note says "a single fade transition is acceptable" but tasks 008/009 do not mention any transition. If a worker adds a fade that briefly changes opacity on a sized element, it should not cause CLS — but if it animates `transform`, position, or size it can. Without a concrete spec the worker may invent a layout-affecting animation.

**Fix applied:** Add to tasks 008 and 009 acceptance criteria: "If any open/close transition is added, it must be `opacity` only — no `transform`, `scale`, `translate`, `width`, `height`, or layout-affecting property — and respect `prefers-reduced-motion`."

### I3. Task 010 / 011 don't specify how the controller injects the inner `<dialog>`
Task 010 says: "creates a `<dialog>` inside, appends the content." Task 011 says the same. But task 008 says `mk-modal` "wraps a server-rendered `<dialog>`" and uses `requireInnerControl` to find it. The controller path is the imperative-only case: `openModal()` builds the element from scratch. The plan needs to specify the precise DOM shape created:
- `<mk-modal size=… dismissible?>` with a child `<dialog>` and content inside the dialog.

Otherwise `requireInnerControl` warnings will fire because the controller creates `<mk-modal>` and synchronously appends it before adding the inner dialog, and `connectedCallback` may execute before content/dialog is in place.

**Fix applied:** Update tasks 010 and 011 with explicit creation order:
1. Create wrapper element via `document.createElement('mk-modal')` (or `mk-drawer`).
2. Create inner `<dialog>` and append it to the wrapper **before** appending the wrapper to `<body>`.
3. Set content on the dialog (innerHTML for string, appendChild for HTMLElement).
4. Set size/placement/dismissible attributes on the wrapper.
5. Append wrapper to `document.body`.
6. Set the `open` attribute on the wrapper (triggering `showModal()` via the updated() hook).

### I4. Task 007 toast eviction triggers an extra `close`/timer race
Task 007 says "max 5 visible. On 6th call, the first (top) toast is removed (its timer cleared)." But the toast's own `disconnectedCallback` (task 006) already clears the timer. The race is: if eviction calls `region.removeChild(li)` while the toast's timer is mid-flight, the timer fires after disconnect and may try to dispatch on a detached node. Task 006 already handles this in `disconnectedCallback`. The plan is consistent — but tasks 006 and 007 must be explicit that eviction goes through `li.remove()` (or `region.removeChild(li)`) so `mk-toast.disconnectedCallback` fires and the controller doesn't need to maintain its own timer map.

**Fix applied:** Clarify task 007: "Eviction calls `oldestLi.remove()`; the inner `<mk-toast>`'s `disconnectedCallback` is responsible for clearing the timer (per task 006). The controller maintains no timer state of its own."

### I5. Task 008 backdrop-click detection technique is fragile
The plan says: "`event.target === dialog` (clicks on `::backdrop` bubble as dialog clicks)." This is the standard trick, but it conflicts with clicks on content with `pointer-events: none`. The plan should reference the technique explicitly and clarify that content children inside the dialog must not have `pointer-events: none`. More importantly, a worker may misinterpret "click handler simply does nothing when `dismissible=false`": if the click handler does nothing, the dialog stays open — but task 008 also says "ignore a backdrop click" while still suppressing `cancel`. The two need to be coordinated.

**Fix applied:** Task 008 receives an Implementation note: "Add a click listener on the inner `<dialog>` that does the following: when `dismissible` is present and `event.target === dialog` (i.e., the click landed on the dialog's padding area = the visible backdrop, since the dialog box has no inner content reaching the edges), call `dialog.close()`. Otherwise no-op. Separately, add a `cancel` event listener that calls `event.preventDefault()` when `dismissible` is absent (suppresses ESC). When `dismissible` is present, do NOT preventDefault and let the cancel proceed."

### I6. Task 002 acceptance criterion `display: contents` may cause subtle CSS edge cases
`display: contents` on `<mk-modal>` means the element doesn't generate a box — children participate in the wrapper's parent's layout context. This is appropriate for the modal-wraps-dialog case BUT it has known accessibility-tree gotchas in older browsers (pre-2022 the element was removed from a11y tree entirely; modern browsers fix this for non-presentational roles). Worth flagging.

**Fix applied:** Add a Risks & Mitigations note in `_plan.md`: "`<mk-modal>` / `<mk-drawer>` use `display: contents`. Modern browsers (Chrome 65+, Firefox 37+, Safari 11.1+) preserve the a11y tree role for `display: contents` elements. We don't assign a role to the wrapper, so this is fine — the inner `<dialog>` carries the role."

### I7. Task 012 CLS fixture must include a server-rendered `<ol class="mk-toast-region">`
The fixture pre-renders 3 toasts (per task 012). But toasts are wrapped in `<li>` inside an `<ol class="mk-toast-region">`. Task 012 says "3 toasts pre-rendered in a region" but doesn't specify the `<ol>` and `<li>` markup. Without those wrappers the toast CSS (which uses `.mk-toast-region` for positioning) won't fire on those toasts.

**Fix applied:** Update task 012 requirements to explicitly say: "fixture pre-renders an `<ol class="mk-toast-region" role="region" aria-live="polite" aria-label="Notifications">` containing 3 `<li>` wrappers, each with a `<mk-toast>` inside, demonstrating the SSR-style markup contract."

### I8. Task 008 / 009 test of "warns once via requireInnerControl" assumes the warn-set is per-test
`requireInnerControl` uses a module-level `WeakSet<HTMLElement>` to dedupe warnings. Across multiple tests, the same element instance won't warn twice; different elements will each warn once. The test description says "warns once" — fine — but the requireInnerControl pattern only fits modal/drawer if the wrapper is expected to contain an inner control. For `mk-modal`, the inner control is `<dialog>` (not a form control). Calling `requireInnerControl(this, 'dialog')` is fine, but the assertion test in task 008 should make explicit it expects a `<dialog>`.

**Fix applied:** Update task 008 requirement labels from "warns once via requireInnerControl when no inner `<dialog>` is present" to "calls `requireInnerControl(this, 'dialog')` and emits a console.warn when no inner `<dialog>` is present in the wrapper." Same change for task 009.

### I9. Tasks 010 / 011 don't define what `dispatchEvent`-based test on `mk-close` looks like under happy-dom
Native `<dialog>.close()` dispatches a `close` event in real browsers; happy-dom's stub does not auto-dispatch this. Task 008 says `mk-modal` "Emits a `mk-close` CustomEvent on the wrapper when the dialog closes" — the test must therefore manually dispatch a `close` event on the dialog to trigger the wrapper's listener. Task 010's test "removes the entire `<mk-modal>` element from the DOM when the mk-close event fires" needs to manually trigger the chain.

**Fix applied:** Add to tasks 010 and 011 implementation notes / test descriptions: "happy-dom does not auto-fire `close` on `dialog.close()`. Tests trigger the close event manually via `dialog.dispatchEvent(new Event('close'))` (or `mk-modal`'s `mk-close` directly) to verify the controller's removal logic." Add the same caveat in task 008/009 risk notes.

### I10. Task 014 must update `mk-toast.md` to clarify the toast role decision
Plan Risks section overrides the original `role="alert"` plan and standardizes on `role="status"` for all variants (including `danger`). Task 006 acceptance and Task 014 docs requirement should mention this — task 014 says "API Reference sections" but doesn't specify documenting the role choice and accessibility rationale.

**Fix applied:** Add an explicit docs requirement in task 014: "the `mk-toast.md` page documents the role decision (`role="status"` for all variants; `aria-live="polite"` on the region; rationale: avoid screen-reader spam from `role="alert"` on every danger toast; consumers can override role server-side for genuinely critical interruptions)."

### I11. `_plan.md` says `--mk-color-on-toast` doesn't exist; toast fg is just `--mk-toast-fg`
Verify that `--mk-toast-fg` does not depend on `--mk-color-on-surface` automatically — task 001 must declare it explicitly. Task 001 lists `--mk-toast-fg` (after fix C2). Good — no further change needed.

### I12. Stub-first ordering correctness
Task 002 creates stubs that `export class MkAlertElement extends MkElement {}` etc. Tasks 003-009 then "expand stubs." But task 002's stub registers the element with `registerBase('mk-alert', MkAlertElement)`. If tasks 003-009 also call `registerBase` (per the file pattern), the second registration will throw `RegistryError("Base class already registered for tag…")` (verified in `packages/frontend/resources/js/registry.ts` line 22). This means the tasks 003-009 must EXTEND the registered class in-place rather than redefine `registerBase`.

**Fix applied:** Update tasks 003-009 acceptance criteria: "The `registerBase('mk-X', MkXElement)` call from task 002 remains; this task adds methods, properties, and CSS to the existing class — it does NOT add a second registerBase call." Add the same warning to `_plan.md` "Stub-first ordering" section.

### I13. Task 014 `:not(:defined)` snippet update count is correct (22 → 28) but missing one item
Plan task 014 says update the snippet to "cover all 28 tag names." Counted: 22 existing + 6 new = 28. Task 002 adds the 6 new ones (mk-alert, mk-toast, mk-spinner, mk-skeleton, mk-modal, mk-drawer) to `base.css`. Verified. Just confirming alignment.

No fix needed.

## Minor (Nice to address)

### M1. Task 006's `tabindex="0"` injection on toast may interfere with keyboard nav
The toast is a transient notification — making it focusable means it appears in tab order. Convention is to NOT make toasts focusable; the close button alone is focusable. Worth re-evaluating.

### M2. Task 003 / 005 / 006 close-button injection lacks key handling
Close button is `<button>` which natively handles Enter/Space. Fine. But `aria-label="Dismiss"` should be locale-aware in the future — flagged for i18n consideration.

### M3. `prefers-reduced-motion` test verification via media-query mock is environment-dependent
Task 004 says "verified via getComputedStyle + media-query mock or a CSS snapshot containing the @media block." happy-dom's media-query support is partial. CSS snapshot is more robust and matches Phase 2/3 conventions.

### M4. The `_plan.md` mentions "Stylelint passes on all new CSS" but stylelint is run via `npm run lint:css` not `stylelint`
Trivial.

## Questions for the Team

### Q1. Why a custom `MutationObserver` over Lit's `@property`?
The original design uses a `MutationObserver` and `#reflecting` guard. Existing components use Lit's reactive `@property` + `updated()` exclusively. Important fix I1 switches to the Lit pattern. Confirm this is the right call — is there a reason the original chose MutationObserver (e.g., to support cases where Lit is loaded after the element is in the DOM)?

### Q2. Should the toast region have a configurable `id` or anchor?
The plan adopts a singleton `<ol.mk-toast-region>`. Multi-region scenarios (e.g., region inside a modal vs. region attached to body) aren't possible. Future need?

### Q3. Should `openModal()` support an `onClose` callback option?
Currently the only way to react to a programmatic-opened modal closing is to listen for `mk-close` on the returned wrapper handle — but the handle returned is only `{ close(): void }`. Adding `onClose?: () => void` to `ModalOptions` would simplify consumer code. Same for `openDrawer`.

### Q4. Is there a need to coordinate toast-region z-index with modal/drawer?
Both can be open at once. Modal/drawer goes to the native top-layer; toast region is `z-index: 1000` in regular stacking. A toast appearing while a modal is open will be visually behind the modal because the top-layer trumps z-index. Is this intentional? (Probably yes — modals are blocking; toasts during blocking flows are unusual.)

### Q5. Should mk-spinner expose a `label` attribute that drives the visually-hidden text?
Currently the spinner expects slotted child text. An attribute like `label="Loading products..."` is more ergonomic for simple cases. Defer to Phase 5 if not needed now.
