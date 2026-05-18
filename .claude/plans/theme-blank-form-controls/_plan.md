# Plan: theme-blank Form Controls (Phase 3)

## Created
2026-05-17

## Status
completed

## Objective
Ship 10 unstyled, CSS-driven form control components for `@markommerce/theme-blank` — `mk-button`, `mk-input`, `mk-textarea`, `mk-select`, `mk-checkbox`, `mk-radio`, `mk-switch`, `mk-field`, `mk-fieldset`, `mk-form` — with a three-layer validation stack, zero CLS, and a Playwright CLS smoke test covering all 10 controls.

## Related Issues
none

## Discovery Notes

**Phase 2 deliverables already on `feature/theme-blank-primitives`** (merged into `feature/theme-blank-form-controls`):
- 12 CSS-driven layout/typography primitives (mk-stack … mk-badge)
- `MkElement` base class in `@markommerce/frontend` (light DOM, no-op default `render()`)
- `registerBase()` / `addMixin()` / `defineAllComponents()` / `requireInnerControl()` registry in `@markommerce/frontend`
- `:not(:defined)` safety net in `base.css` for all 12 Phase 2 tags
- Playwright CLS infrastructure in `tests/Browser/`
- `components/index.ts` side-effect entry imports all 12 Phase 2 primitives

**Architectural decisions locked during brainstorm (Phase 3 brief):**

1. **Component pattern** — same as `mk-link`: server-rendered native control inside the custom element wrapper. Single-tag mode is out of scope. JS augments; it never replaces children. CLS = 0.
2. **Attribute split** — wrapper = presentational (`variant`, `size`, `loading`); inner control = semantic/form (`name`, `value`, `required`, `pattern`, `disabled`, …). CSS uses `:has()` to react to inner state — no JS mirroring.
3. **Invalid-state hybrid** — CSS primary uses `:user-invalid`/`:user-valid` (Chrome 119+, FF 88+, Safari 16.4+); JS fallback: `mk-field` writes `data-touched` after first blur so `mk-field[data-touched]:has(input:invalid)` works in older browsers. `mk-field` always writes `data-state="pristine"|"invalid"|"valid"|"validating"`.
4. **Three-layer validation stack**:
   - Layer A: Native HTML5 constraints (`required`, `pattern`, `type`, …) — `mk-field` surfaces `validationMessage` into `[data-mk-error]`.
   - Layer B: Sync custom validators via `field.addValidator(name, fn)` — non-null return calls `setCustomValidity()`.
   - Layer C: Async validators via `field.addAsyncValidator(name, asyncFn)` — run on blur (debounced ~300 ms) and on form submit; `data-state="validating"` during the request.
5. **`mk-form` orchestrator** — sets `novalidate` on the inner `<form>`, awaits all async validators in parallel on submit, focuses first invalid field, emits `mk-submit` with `FormData` and `mk-invalid` listing invalid fields.
6. **`requireInnerControl()` helper** — already defined in `@markommerce/frontend`; used by every form wrapper component to `console.warn` once per element when the inner native control is missing.
7. **`mk-switch`** — wraps `<input type="checkbox">`; `connectedCallback` sets `role="switch"` on the inner input if not already present.
8. **`mk-button` loading** — `loading` boolean attribute; JS sets `disabled` on inner `<button>` and `aria-busy="true"` on wrapper when true; reverses on false.
9. **`mk-quantity-input` deferred** to a commerce-specific phase.

## Scope

### In Scope
- **New form design tokens** in `tokens.css`: `--mk-color-focus-ring`, `--mk-radius-input`, `--mk-input-height-{sm|base|lg}`, plus per-component `--mk-{button|input|field}-*` fine-grained overrides.
- **`requireInnerControl()` helper** exported from `@markommerce/frontend` with unit tests.
- **10 Lit element classes** (`MkButtonElement` … `MkFormElement`) in `packages/theme-blank/resources/js/components/`, each in its own file, extending `MkElement`.
- **10 component CSS files** in `packages/theme-blank/resources/css/components/`, all inside `@layer components`.
- **Extended `components/index.ts`** importing all 10 new form modules.
- **Extended `base.css` `:not(:defined)` safety-net** covering all 10 new tag names.
- **`mk-field` validation orchestrator** implementing all three validation layers.
- **`mk-form` orchestrator** with `novalidate`, async-validator coordination, `mk-submit`/`mk-invalid` events.
- **Per-component Vitest unit tests** for all 10 components.
- **Playwright CLS fixture + spec** (`forms-page.html` + `forms-cls.spec.ts`) asserting CLS === 0 pre- and post-upgrade for all 10 controls.
- **10 per-component docs pages** at `docs/src/content/docs/packages/theme-blank/mk-{name}.md`.
- **Updated docs index** with a `## Form Controls` section.
- **Frontend-demo update** — extend `/markommerce/_demo` with a "Form Controls" section.

### Out of Scope
- `mk-quantity-input` (commerce phase)
- Latte partials for form components
- Server-side PHP validation integration
- AJAX form submission beyond emitting `mk-submit`
- Custom listbox/combobox (keep native `<select>`)
- `mk-file-input`, date pickers, color pickers, range sliders
- Shadow-DOM components
- Icons inside inputs

## Success Criteria
- [ ] All 10 form custom elements register, are styled via CSS-only, and produce zero CLS in the Playwright suite (pre- and post-upgrade)
- [ ] `requireInnerControl()` helper exported from `@markommerce/frontend` and tested
- [ ] `:not(:defined)` safety-net rule in `base.css` covers all 10 new tag names
- [ ] `mk-field` implements all three validation layers (native CV, sync validators, async validators) with tests
- [ ] `mk-form` sets `novalidate`, coordinates async validators, emits `mk-submit`/`mk-invalid`, with tests
- [ ] `mk-button` loading attribute toggles `disabled`/`aria-busy` with tests
- [ ] `mk-switch` sets `role="switch"` on inner input in `connectedCallback` with tests
- [ ] Every component has a Vitest unit test asserting registration + light-DOM preservation
- [ ] Every component has a docs page with required sections; docs index links to all 10
- [ ] `/markommerce/_demo` renders a "Form Controls" section showcasing all 10 components
- [ ] `npm test` passes (Vitest suite, no coverage regression)
- [ ] `npm run test:cls` passes (Playwright — original specs AND new `forms-cls.spec.ts`)
- [ ] `composer test` passes (PHP suite, no regression)
- [ ] Stylelint passes on all new CSS

## Task Overview

| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Add form design tokens to tokens.css | - | completed |
| 002 | Add `requireInnerControl()` helper to `@markommerce/frontend` | - | completed |
| 003 | Scaffold 10 component stubs + extend `components/index.ts` + `base.css` `:not(:defined)` safety net | 001, 002 | completed |
| 004 | `mk-button` — variant/size/loading form button | 002, 003 | completed |
| 005 | `mk-input` — variant/size text input wrapper | 002, 003 | completed |
| 006 | `mk-textarea` — variant/size textarea wrapper | 002, 003 | completed |
| 007 | `mk-select` — variant/size select wrapper | 002, 003 | completed |
| 008 | `mk-checkbox` — styled checkbox wrapper | 002, 003 | completed |
| 009 | `mk-radio` — styled radio wrapper | 002, 003 | completed |
| 010 | `mk-switch` — toggle-switch checkbox wrapper | 002, 003 | completed |
| 011 | `mk-field` — label/control/hint/error orchestrator with 3-layer validation | 002, 003 | completed |
| 012 | `mk-fieldset` — group wrapper for related fields | 002, 003 | completed |
| 013 | `mk-form` — form orchestrator with novalidate + submit coordination | 003, 011 | completed |
| 014 | Playwright CLS fixture + spec covering all 10 form controls | 004, 005, 006, 007, 008, 009, 010, 011, 012, 013 | completed |
| 015 | Extend `frontend-demo` route with "Form Controls" section | 004, 005, 006, 007, 008, 009, 010, 011, 012, 013 | completed |
| 016 | Docs pages (10 components) + docs index Form Controls section | 004, 005, 006, 007, 008, 009, 010, 011, 012, 013 | completed |
| 017 | Scaffold `theme-blank-demo` package skeleton + register in root composer.json | - | completed |
| 018 | PHP infra (Config, Middleware, Layout, Controller, Component) | 017 | completed |
| 019 | Latte views (`base.latte` + `showcase.latte` with primitives + form controls content) | 018 | completed |
| 020 | Vite + npm wiring (`main.ts`, `index.ts`, hand-maintained extensions placeholder, root `vite.config.ts` multi-entry update, theme-blank `components/index.ts` + `base.css` form-control bug fix) | 017 | completed |
| 021 | Migrate per-element + Form Controls tests out of `frontend-demo` into `theme-blank-demo`; strip primitives + form controls sections from `frontend-demo/counter.latte` | 019, 020 | completed |
| 022 | `theme-blank-demo` README + docs page + update `theme-blank` index docs + update `frontend-demo` README (final task) | 021 | completed |

## Architecture Notes

### CLS-prevention rule (same as Phase 2)
Every component renders correctly without JavaScript. JS upgrade must not shift layout. The `:not(:defined)` CSS in `@layer base` and the `@layer components` tag-selector rules supply identical layout pre- and post-upgrade.

### Attribute model
Presentational attributes (`variant`, `size`, `loading`) live on the wrapper and are reflected via `@property({ reflect: true })`. Semantic attributes (`name`, `required`, `disabled`, …) live on the inner native element. CSS uses `:has()` to react to inner state — no JS mirroring.

### Validation contract
`mk-field` is the single source of validation truth. `mk-form` delegates to `mk-field` instances. The native `<form>` element always has `novalidate` when inside `mk-form`. `setCustomValidity()` is the bridge between all three validator layers and the browser's constraint-validation API.

### CSS structure
```
resources/css/components/
  mk-button.css      # @layer components
  mk-input.css       # @layer components
  mk-textarea.css    # @layer components
  mk-select.css      # @layer components
  mk-checkbox.css    # @layer components
  mk-radio.css       # @layer components
  mk-switch.css      # @layer components
  mk-field.css       # @layer components
  mk-fieldset.css    # @layer components
  mk-form.css        # @layer components (minimal — layout only)
```

### Debounce implementation
`mk-field` implements a simple internal debounce for async validators using `setTimeout`/`clearTimeout`. No external debounce library.

### Stub-first task ordering
Task 003 scaffolds **empty stubs** for all 10 component `.ts` and `.css` files (matches Phase 2 precedent in `packages/theme-blank/resources/js/components/index.test.ts` lines 50-72). Tasks 004-013 *expand* these stubs rather than create them from scratch. This prevents the circular ordering where 003 (which imports `./mk-button`, `./mk-input`, …) would fail to compile until 004-013 complete.

### Browser baseline
`:user-invalid` / `:user-valid` — Chrome 119+, FF 88+, Safari 16.4+. Same floor as `@container` (established in Phase 2). Document in all affected component docs pages.

## Risks & Mitigations

- **Risk:** `mk-field` async validators race condition — blur triggers debounced async validator, user immediately submits. Both paths run concurrently.
  - **Mitigation:** `mk-form` collects all `mk-field` instances and awaits their `.validate()` method (which cancels and re-runs all validators synchronously on submit). Debounce is cleared on submit. `mk-form` also uses a `#submitting` reentrancy guard to ignore duplicate submit events while a previous submit is still pending.

- **Risk:** Layer A (native HTML5 constraints like `required`, `pattern`) never surfaces in `[data-mk-error]` because `mk-form` sets `novalidate` on the inner form, which suppresses the browser's `invalid` event dispatch on native submit.
  - **Mitigation:** `mk-field.validate()` explicitly calls `control.checkValidity()` (which DOES dispatch `invalid` even on novalidate forms because it is an explicit API call), then reads `control.validationMessage` and writes it via `#setError`. The `invalid` event listener still serves the case where `mk-field` is used outside `mk-form`.

- **Risk:** `mk-form` `novalidate` applied after the `<form>` element is already in the DOM causes a brief window where native validation could fire.
  - **Mitigation:** `novalidate` is set synchronously in `connectedCallback`, which runs before the browser's next task. The `submit` event listener intercepts before native validation runs because `novalidate` was already applied.

- **Risk:** `mk-field` cannot find its inner control when used with `mk-input`/`mk-textarea`/`mk-select` wrappers (the native element is a grandchild, not a child).
  - **Mitigation:** `findControl()` uses `querySelector('input, textarea, select')` — descends the full subtree. Verified to find `mk-input > input` from `mk-field`'s perspective.

- **Risk:** Multiple `addValidator` calls with the same name silently overwrite or stack.
  - **Mitigation:** Validators stored in a `Map<string, fn>` keyed by name — same name overwrites silently (last-write-wins), which is the intuitive behavior for re-registering a validator.

- **Risk:** `mk-switch` setting `role="switch"` on the inner input conflicts with explicit `role` set by the server.
  - **Mitigation:** `connectedCallback` checks `if (!input.hasAttribute('role'))` before setting — same guard pattern as `mk-heading`'s ARIA injection.

- **Risk:** Stylelint does not know the new `--mk-*` token names for form controls.
  - **Mitigation:** Stylelint has no allow-list of token names; the only relevant rule (`property-no-unknown` in `stylelint.config.js`) ignores all custom properties via `/^--/`, and `custom-property-no-missing-var-function` is disabled. New `--mk-*` tokens are accepted automatically.
