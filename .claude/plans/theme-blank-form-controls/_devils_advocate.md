# Devil's Advocate Review: theme-blank-form-controls

## Critical (Must fix before building)

### 1. Circular task ordering between Task 003 and Tasks 004-013

**Affects**: Task 003, 004, 005, 006, 007, 008, 009, 010, 011, 012, 013.

Task 003 appends 10 import statements (`import './mk-button'`, etc.) to `components/index.ts`. Those modules do not yet exist when 003 runs because tasks 004-013 depend on 003 — so 003 must finish first. But once 003 finishes, `vitest`/`tsc` will fail because the imports resolve to non-existent files.

Phase 2's pattern (visible in `index.test.ts` lines 50-72) was to scaffold stub `.ts` and `.css` files first, then expand them. The Phase 3 plan never specifies this, and component tasks each "Create new" the same files that 003 already needs to import.

**Fix applied**: Task 003 now creates *stub* `.ts` + `.css` files for each new tag (empty class extending `MkElement`, empty `@layer components {}` block). Tasks 004-013 are restated as **expanding** these stubs rather than creating them from scratch. Acceptance criteria for 003 include "TypeScript compiles after running 003 in isolation". The component-task dependencies (`002, 003`) are retained but the meaning is now "after 003 has scaffolded the stubs you fill in".

### 2. `mk-field` never surfaces native HTML5 validation messages

**Affects**: Task 011, Task 013.

The `mk-form` orchestrator sets `novalidate` on the inner `<form>`. With `novalidate`, the browser never fires the `invalid` event during submit (Layer A's chosen hook), so the listener `#onInvalid` is dead code in the in-form code path. Native messages from `required`, `pattern`, `minLength`, etc. would never appear in `[data-mk-error]`. `mk-field.validate()` only calls sync + async validators, but it neither calls `control.checkValidity()` nor reads `control.validationMessage` directly — Layer A is silently dropped.

**Fix applied**: Task 011 `validate()` now explicitly calls `this.#control.checkValidity()` to allow Layer A to populate `validationMessage`, then unconditionally reads `this.#control.validationMessage` after sync + async runs and writes it via `#setError`. Added a requirement `it populates [data-mk-error] with the native validationMessage from a required field when validate() is called`. Also documented that `#onInvalid` is the path used outside `mk-form` (when there is no parent `mk-form` and the page's `<form>` triggers native submit).

### 3. Tests for `mk-form` will fail unless they also import `mk-field`

**Affects**: Task 013.

`mk-form.test.ts` exercises `validate()` on inner `mk-field` elements. If the test imports only `./mk-form`, the `<mk-field>` children are upgraded to `HTMLElement` (no `validate()` method) and `f.validate()` throws `TypeError: f.validate is not a function`.

**Fix applied**: Task 013 context now requires `import './mk-field'` (and necessary inner wrappers used in fixtures) inside `mk-form.test.ts` before `defineAllComponents()`. Added an explicit note in the Context section.

### 4. `data-touched` is described but never written or used

**Affects**: Task 011.

The plan's Architectural decision #3 cites `mk-field[data-touched]:has(input:invalid)` as the fallback for browsers without `:user-invalid`. The CSS in task 011 does not contain any `[data-touched]` selector, and the TypeScript shape never sets `dataset.touched`. One test "it sets data-touched on the element after the first blur event on the inner control" exists, but no CSS hook consumes the attribute.

**Fix applied**: Task 011 now (a) adds `this.dataset['touched'] = ''` inside the first invocation of `#onBlur` (idempotent on later blurs), and (b) adds the CSS rule `mk-field[data-touched]:has(input:invalid):not(:focus-within) > [data-mk-error] { display: block; }` to mk-field.css as the documented fallback hook. Updated requirements to assert both behaviors.

## Important (Should fix before building)

### 5. Component-task dependencies under-specify control parsing order

**Affects**: Task 011, Task 013.

`mk-field`'s `connectedCallback` calls `this.querySelector('input, textarea, select')`. When `mk-field` wraps `mk-input` wraps `<input>`, the inner `<input>` exists in the DOM tree at the time `mk-field`'s `connectedCallback` runs *during HTML parsing* (custom-element upgrades happen after the closing tag is seen). But when constructed programmatically via `document.createElement('mk-field')` and `appendChild(mkInput)`, the order is: create field (no children) → create input wrapper (no children) → append input to wrapper → append wrapper to field → append field to body. `connectedCallback` on `mk-field` fires when the field is appended to a connected ancestor. By that point the inner control IS present.

But the test for "it finds the first input descendant as the control" must use a sequence that adds children **before** appending the field to the body. This is already the way `mk-link.test.ts` and `mk-heading.test.ts` exercise inner DOM. Task 011 does not call this out and the test scaffolds may produce flaky results.

**Fix applied**: Task 011 requirements now specify the test pattern explicitly: "construct mk-field with children attached, THEN append to document.body, THEN assert".

### 6. `super.connectedCallback()` ordering inconsistent with existing components

**Affects**: Task 011, Task 013 (and to a lesser extent 004, 010).

`mk-heading.ts` and `mk-cover.ts` perform their imperative DOM/attribute work *before* `super.connectedCallback()`. The plan for `mk-field` calls `super.connectedCallback()` *first* then queries the DOM and adds listeners. Lit's `super.connectedCallback()` schedules an update; for `MkElement` whose `render()` returns `nothing`, the practical effect is a no-op on light-DOM children, but the order is inconsistent with the repo's existing pattern.

**Fix applied**: Updated Task 011 and Task 013 TypeScript shapes to call `super.connectedCallback()` last (after attribute work), matching the existing pattern in `mk-cover.ts` and `mk-heading.ts`. Tasks 004 and 010 already call `super.connectedCallback()` first — left as-is because they only call `requireInnerControl`/`syncLoading` which do not depend on Lit lifecycle timing.

### 7. `mk-form` race condition: double-submit while validators are pending

**Affects**: Task 013.

If a user clicks the submit button twice rapidly, the first `submit` handler is still awaiting `Promise.all(...)`. The second handler enters concurrently. Both will eventually dispatch `mk-submit` (or one valid + one invalid). The submit button is not disabled during validation.

**Fix applied**: Task 013 now adds a `#submitting` reentrancy guard. While `#submitting === true`, the handler immediately `event.preventDefault()`s and returns without re-entering validation. Added requirement `it ignores concurrent submit events while a previous submit is still pending`.

### 8. Task 014 fixture/upgrade-stub list is incomplete

**Affects**: Task 014.

The `primitives-cls.spec.ts` upgrade-simulation block sets per-tag length-attribute → CSS variable mappings (`mk-grid → --mk-grid-min`, etc.). The Phase 3 components have analogous side-effects:
- `mk-field` sets `data-state="pristine"` on connect.
- `mk-switch` sets `role="switch"` on the inner input.
- `mk-button` (with `loading="true"` parsed from markup, if present) sets `aria-busy` and inner `disabled`.

If the upgrade-simulation stubs don't perform these side-effects, the post-upgrade DOM differs from real upgrades and the test does not actually validate real behavior. CLS will still be zero (because these side-effects don't shift layout) but the test is misleading.

**Fix applied**: Task 014 spec section now lists the per-tag upgrade side-effects the simulation must include. Added an explicit requirement: "the upgrade-simulation stubs apply mk-field data-state=pristine, mk-switch role=switch on inner input, and a no-op for mk-button loading-state since the fixture does not set loading=true".

### 9. Task 015 inline `<script>` will collide with Latte syntax

**Affects**: Task 015.

The task says the form should be "wired with inline `<script>` to demonstrate `mk-submit` event handling (console.log the FormData entries)". Latte parses `{...}` as expressions; an inline script containing `{` (object literals, template strings, arrow function bodies) will throw a Latte compile error. Existing demo Latte files have no inline scripts.

**Fix applied**: Task 015 now mandates wrapping any inline `<script>` block in Latte's `{syntax off}…{/syntax}` to disable curly-brace parsing inside the script.

### 10. Risks section misstates how stylelint validates new tokens

**Affects**: `_plan.md` Risks section (last bullet).

The plan claims stylelint is configured with a `--mk-*` pattern allowlist. `stylelint.config.js` has `property-no-unknown` ignoring `/^--/` (declaring any `--*` property) and disables `custom-property-no-missing-var-function`. There is no allowlist *for usage*; usages were never restricted. The mitigation is correct in outcome (new tokens "just work") but the explanation is wrong and could mislead a maintainer.

**Fix applied**: `_plan.md` risk wording corrected to "Stylelint has no allow-list of token names; the only relevant rule (`property-no-unknown`) ignores all custom properties via `/^--/`, so new `--mk-*` tokens are accepted automatically."

### 11. Task 015 needs broader per-tag assertions to match Phase 2 precedent

**Affects**: Task 015.

`DemoControllerTest.php` already has ~12 separate `it('...contains at least one mk-X element')` tests — one per Phase 2 primitive. Task 015's requirement list mentions only "renders mk-button examples", "mk-input examples", "complete mk-form example" — four tests for ten components. Workers may interpret this as "only test four"; the existing precedent is one assertion per component.

**Fix applied**: Task 015 requirements expanded to one `it ...contains at least one <mk-X` assertion per component (10 in total), plus the section-heading and complete-form assertion. Acceptance criteria updated.

### 12. `mk-field` `dataset` mutation will not survive a Lit re-render trigger

**Affects**: Task 011 (informational, plan already notes this).

Task 011 says "No `@property` for `data-state` or `data-touched` — these are managed imperatively to avoid Lit re-renders resetting them." That guidance is correct for *attributes* set via `setAttribute`. `dataset.state = 'x'` writes the `data-state` attribute, but Lit's reactive update never touches attributes outside its declared `@property` decorators. So the guidance is fine — but the doc text says "to avoid Lit re-renders resetting them," which would only be a risk if `render()` returned a template that overwrites those attributes. Our base `render()` returns `nothing`, so there is no risk.

**Fix applied**: Task 011 wording clarified to: "imperative `dataset` mutation is the chosen mechanism because `data-state` and `data-touched` need to react to events, not to property changes; they do not need to be reactive properties." No behavioral change.

### 13. Task 010 (`mk-switch`) and Task 008 (`mk-checkbox`) share an over-permissive selector

**Affects**: Task 008, Task 010.

The selector `'input[type="checkbox"], input:not([type])'` matches text inputs whose type was not declared (HTML default is `text`, but `input:not([type])` still matches them — the absence of the type attribute, not its computed value). A consumer who writes `<mk-checkbox><input id="foo"></mk-checkbox>` gets a tinted but functionally-text input. This is a footgun.

**Fix applied**: Tasks 008 and 010 selectors narrowed to `'input[type="checkbox"]'` and `'input[type="radio"]'` respectively, with a documentation note that consumers must specify `type="checkbox"` / `type="radio"` explicitly. The `requireInnerControl` warning will fire if they don't.

### 14. `mk-field` `validate()` flow does not clear stale custom validity

**Affects**: Task 011.

When `validate()` runs, the first step `control.setCustomValidity('')` clears prior custom messages — good. But if Layer A native validity (e.g., a `required` field becomes empty mid-flow) is currently failing, calling `setCustomValidity('')` resets only the custom slot; native validity is unchanged. After sync validators run (all pass), `control.validity.valid` may still be `false` because native CV is in violation. The plan's "if (still valid) await async" branch must check `control.validity.valid` (not just sync-validator results) — which it does. **But** the resulting `[data-mk-error]` text in `#setError` must use `control.validationMessage` (native message), not the (null) sync result.

**Fix applied**: Task 011 `validate()` Logic Detail now says: after sync + async, write `this.#setError(this.#control.validationMessage || null)` so that native messages flow through to the DOM. Added requirement `it surfaces the native validationMessage of a required-but-empty control through validate()`.

## Minor (Nice to address)

### 15. `composed: true` on `mk-form` events is unnecessary

`mk-form` is light-DOM. `composed: true` only matters when an event needs to cross a shadow root boundary. Setting it is harmless but misleading. Not fixed — keeping symmetry with potential future shadow-DOM consumers.

### 16. Task 011 `mk-field` `#syncValidators` ordering

The plan says "Run each sync validator in insertion order" but `Map` iteration order is insertion order by spec, so this is automatically correct. Worth a one-line note in the implementation for future maintainers.

### 17. Task 014 fixture may need `<head>` `<style>` to inline the new form-token CSS

The plan instructs the fixture to "inline tokens.css" — but the new form tokens added in task 001 must also be inlined. The Phase 2 fixture used a flat dump and will not auto-update. Worker must remember to copy the new tokens.

### 18. Task 011 `Map<string, fn>` last-write-wins

The mitigation under "Risk: Multiple `addValidator` calls with the same name silently overwrite or stack" promises overwrite semantics. The test exists. But there is no `removeValidator(name)` API. Consumers cannot un-register validators short of clearing the whole Map. Out of scope for this phase, but worth noting in docs.

### 19. Task 011 `#onInput` purpose unspecified

The plan adds an `input` event listener but never describes what it does. Likely it should clear `data-state` back to a non-error state once the user starts editing, but the plan is silent. Implementer will have to guess.

### 20. Task 002 `requireInnerControl` warning fires once per element, but components subscribe in `connectedCallback`

If an element is disconnected and reconnected after children are added later, the WeakSet has it marked — no second warning. This is intentional ("once per element instance"), but the implementer may want a second chance after `disconnectedCallback`. Not changed; the current behavior is consistent with "once per element".

## Questions for the Team

- **Q1:** Should `mk-form` disable the inner submit button while `Promise.all(validate())` is pending? (Currently the plan does not — the `#submitting` reentrancy guard suffices, but the UX is "click submit, nothing visible, click again.")
- **Q2:** Is there a server-side requirement to receive the `mk-submit` event and submit normally? Currently `mk-form` always `preventDefault()`s. Real forms would need to call `this.#form.submit()` after dispatching `mk-submit` to actually navigate/POST.
- **Q3:** Should `mk-field` fire a `mk-validate` or `mk-state-change` event when transitioning between `pristine` / `invalid` / `valid` / `validating`? Currently it only mutates DOM attributes; no listener event is provided.
- **Q4:** Should `mk-checkbox`/`mk-radio` reflect their inner `<input>`'s `:checked` state to the wrapper via `:has(input:checked)` for downstream styling? Plan does not include a `[data-checked]` or analogous hook.
- **Q5:** Browser baseline says `:user-invalid` requires Safari 16.4+ — Phase 2's `@container` floor was also Safari 16.4+. Is this still the project's official floor? If so, the `mk-field[data-touched]` JS fallback may be unnecessary belt-and-braces.
