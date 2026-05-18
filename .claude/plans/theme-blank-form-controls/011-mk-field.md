# Task 011: `mk-field` — Validation Orchestrator

**Status**: completed
**Depends on**: 002, 003
**Retry count**: 0

## Description
Create `mk-field` — the central validation orchestrator for Phase 3. It wraps a label, a form control (native or inside a wrapper like `mk-input`), a hint slot, and an error slot. It implements all three validation layers: native HTML5 constraints (Layer A), sync custom validators (Layer B), and async validators with debounce (Layer C). It is the most JS-intensive component in this phase.

## Context
- Related files (stubs scaffolded by task 003, expand them here):
  - `packages/theme-blank/resources/js/components/mk-field.ts` (stub from 003 — extend)
  - `packages/theme-blank/resources/css/components/mk-field.css` (stub from 003 — fill in)
  - New: `packages/theme-blank/resources/js/components/mk-field.test.ts`
- Patterns to follow: `mk-heading.ts` for `connectedCallback` + event-listener lifecycle, `mk-badge.ts` for the class shape. Imperative DOM work happens BEFORE `super.connectedCallback()`, matching the existing pattern in `mk-cover.ts` and `mk-heading.ts`.
- `data-state` and `data-touched` are mutated imperatively via `this.dataset` because they need to react to DOM events (blur/input/invalid), not to property changes. They are not declared as `@property` decorators because they need not be reactive properties.
- `requireInnerControl` is NOT called here — `mk-field` finds the control via `querySelector` internally (the control may be a grandchild inside `mk-input` etc.).

## DOM Shape

```html
<mk-field>
  <label for="email">Email</label>
  <mk-input><input id="email" name="email" type="email" required></mk-input>
  <small slot="hint">We'll never share it.</small>
  <small data-mk-error></small>
</mk-field>
```

The `[data-mk-error]` element is optional — if absent, `mk-field` skips error-message injection.

## TypeScript Shape

```typescript
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-field.css';

type SyncValidator = (value: string, form: FormData | null) => string | null;
type AsyncValidator = (value: string, form: FormData | null) => Promise<string | null>;

export class MkFieldElement extends MkElement {
  readonly #syncValidators = new Map<string, SyncValidator>();
  readonly #asyncValidators = new Map<string, AsyncValidator>();
  #debounceTimer: ReturnType<typeof setTimeout> | undefined;
  #control: HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement | null = null;

  addValidator(name: string, fn: SyncValidator): void { … }
  addAsyncValidator(name: string, fn: AsyncValidator): void { … }

  /** Called by mk-form on submit — cancels debounce, runs all validators synchronously then async in parallel. */
  async validate(): Promise<boolean> { … }

  override connectedCallback(): void {
    this.#control = this.querySelector('input, textarea, select') as … | null;
    this.dataset['state'] = 'pristine';
    this.#control?.addEventListener('blur', this.#onBlur);
    this.#control?.addEventListener('input', this.#onInput);
    this.#control?.addEventListener('invalid', this.#onInvalid);
    super.connectedCallback();
  }

  override disconnectedCallback(): void {
    this.#control?.removeEventListener('blur', this.#onBlur);
    this.#control?.removeEventListener('input', this.#onInput);
    this.#control?.removeEventListener('invalid', this.#onInvalid);
    clearTimeout(this.#debounceTimer);
    super.disconnectedCallback();
  }

  /** On blur: set data-touched (sticky), run sync validators, then debounced async. */
  #onBlur = async (): Promise<void> => {
    this.dataset['touched'] = '';
    // sync validators → setCustomValidity → #setError
    // schedule async via #runAsyncValidators (debounced)
  };

  /** On input: clear error state once user starts editing again. */
  #onInput = (): void => {
    // If current state is "invalid", optimistically downgrade to "pristine"
    // until next blur — prevents stale error display while user types.
    // Implementation: clear setCustomValidity, clear [data-mk-error] textContent,
    // set data-state="pristine".
  };

  /** Layer A: fired by browser when control is invalid during native submit
   *  OR an explicit checkValidity() call. Surface the native message. */
  #onInvalid = (): void => { … };

  #runSyncValidators(): string | null { … }
  #runAsyncValidators(): void { … }  // debounced, sets data-state="validating"
  #setError(message: string | null): void { … }
}

registerBase('mk-field', MkFieldElement);
```

## CSS Shape (`mk-field.css`)

```css
@layer components {
  mk-field {
    display: flex;
    flex-direction: column;
    gap: var(--mk-field-gap);
  }

  mk-field [data-mk-error] {
    display: none;
    color: var(--mk-field-error-color);
    font-size: var(--mk-font-size-sm);
  }

  mk-field[data-state="invalid"] [data-mk-error] {
    display: block;
  }

  /* Fallback for browsers without :user-invalid support — surface native
   * invalidity after the user has touched the field, but not while focused. */
  mk-field[data-touched]:has(input:invalid, textarea:invalid, select:invalid):not(:focus-within) [data-mk-error] {
    display: block;
  }

  mk-field [slot="hint"] {
    color: var(--mk-field-hint-color);
    font-size: var(--mk-font-size-sm);
  }

  mk-field[data-state="validating"] {
    /* consumer can hook here for a spinner */
    opacity: 0.8;
  }
}
```

## Validation Logic Detail

### Layer A (native CV)
`mk-field` listens for `invalid` event on the control (fired by browser when form submits or `checkValidity()` is called). On `invalid`, it reads `control.validationMessage` and writes it to `[data-mk-error]`, sets `data-state="invalid"`.

When `mk-field` is inside a `mk-form`, the `<form>` has `novalidate` so the browser will NOT fire `invalid` events on native submit. To surface Layer A messages in that case, `validate()` explicitly calls `control.checkValidity()` (which DOES dispatch `invalid` even on novalidate forms because it is an explicit API call), then reads `control.validationMessage` after sync + async validators have run. The `invalid` event listener remains valuable for the case where `mk-field` is used WITHOUT `mk-form` (raw native form with native validation enabled).

### Layer B (sync validators)
Stored in `#syncValidators` Map. On `blur` and on `validate()`:
1. `control.setCustomValidity('')` — clear previous custom message
2. Run each sync validator in insertion order
3. First non-null return → `control.setCustomValidity(message)`; stop
4. Re-read `control.validationMessage` and call `#setError()`

### Layer C (async validators)
On `blur`: clear debounce timer, set new timer (300 ms). On fire:
1. Set `data-state="validating"`
2. `Promise.all([...#asyncValidators.values()].map(fn => fn(value, formData)))`
3. First non-null result → `control.setCustomValidity(message)`
4. Update `data-state` + `[data-mk-error]`

On `validate()` (called by `mk-form`):
- `clearTimeout(#debounceTimer)`
- `control.setCustomValidity('')` (clear stale custom message)
- Run sync validators; first non-null message → `control.setCustomValidity(msg)`, stop
- If `control.validity.valid` is still true, `await Promise.all(async validators)`; first non-null → `setCustomValidity(msg)`
- Call `control.checkValidity()` to populate `validationMessage` from Layer A constraints (e.g. `required`, `pattern`)
- Read `control.validationMessage` and call `#setError(this.#control.validationMessage || null)` so native messages reach `[data-mk-error]`
- Return `control.validity.valid`

### `#setError(message: string | null)`
- If `message` → write to `[data-mk-error]` textContent, set `data-state="invalid"`
- If null → clear `[data-mk-error]`, set `data-state="valid"` if `control.validity.valid`, else `pristine`

## Requirements (Test Descriptions)

**Test scaffolding pattern**: For tests that exercise inner-control discovery, build the DOM tree first (`appendChild` children to `mk-field`) and then `document.body.appendChild(field)`. The `connectedCallback` fires on body-append and at that point all descendants are present. Awaiting `el.updateComplete` after append is recommended.

- [ ] `it registers under the tag name "mk-field"`
- [ ] `it MkFieldElement extends MkElement`
- [ ] `it finds the first input descendant as the control`
- [ ] `it finds a textarea descendant when no input is present`
- [ ] `it finds a select descendant when no input or textarea is present`
- [ ] `it sets data-state="pristine" on connectedCallback`
- [ ] `it sets data-touched on the element after the first blur event on the inner control`
- [ ] `it does not remove data-touched on subsequent blur events (sticky)`
- [ ] `it the data-touched + :has(input:invalid):not(:focus-within) CSS rule is present in mk-field.css as the older-browser fallback`
- [ ] `it populates [data-mk-error] with the native validationMessage when the control fires an invalid event`
- [ ] `it sets data-state="invalid" when the control fires an invalid event`
- [ ] `it clears [data-mk-error] and sets data-state="valid" when the control becomes valid after being invalid`
- [ ] `it addValidator stores a sync validator by name`
- [ ] `it addValidator — sync validator returning a non-null message calls setCustomValidity with that message`
- [ ] `it addValidator — sync validator returning null clears setCustomValidity`
- [ ] `it addValidator — calling addValidator with the same name overwrites the previous validator`
- [ ] `it addAsyncValidator sets data-state="validating" while the async function is pending`
- [ ] `it addAsyncValidator sets data-state="invalid" and populates [data-mk-error] when the async validator returns a message`
- [ ] `it addAsyncValidator sets data-state="valid" when the async validator returns null`
- [ ] `it validate() cancels any pending debounce and awaits all async validators`
- [ ] `it validate() returns true when all validators pass`
- [ ] `it validate() returns false when a sync validator fails`
- [ ] `it validate() returns false when an async validator fails`
- [ ] `it validate() populates [data-mk-error] with the native validationMessage from a required-but-empty control (Layer A surfaces through validate())`
- [ ] `it validate() returns false when a required control is empty (native CV via checkValidity)`
- [ ] `it removes event listeners on disconnectedCallback`
- [ ] `it the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-field.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- Sync validators use `Map` keyed by name (last-write-wins for duplicates)
- Async validators debounced with `setTimeout`/`clearTimeout` — no external debounce library
- `disconnectedCallback` clears the debounce timer and removes all event listeners
- No `@property` decorators for `data-state`/`data-touched` — managed imperatively
- CSS uses only `--mk-*` tokens
- All CSS inside `@layer components`
- TypeScript compiles without errors (use `#private` class fields, not underscore convention)
