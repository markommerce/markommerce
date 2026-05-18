# Task 013: `mk-form` — Form Orchestrator

**Status**: completed
**Depends on**: 003, 011
**Retry count**: 0

## Description
Create `mk-form` — the top-level form orchestrator. It sets `novalidate` on the inner `<form>`, intercepts the `submit` event, coordinates async validation across all `mk-field` descendants in parallel, focuses the first invalid field, and emits `mk-submit` (with `FormData`) on success or `mk-invalid` (with the list of invalid fields) on failure.

## Context
- Related files (stubs scaffolded by task 003, expand them here):
  - `packages/theme-blank/resources/js/components/mk-form.ts` (stub from 003 — extend)
  - `packages/theme-blank/resources/css/components/mk-form.css` (stub from 003 — fill in)
  - New: `packages/theme-blank/resources/js/components/mk-form.test.ts`
- Patterns to follow: `mk-heading.ts` for `connectedCallback`/`disconnectedCallback` lifecycle; task 011 (`mk-field`) for the `validate()` method it calls.
- `requireInnerControl` from `@markommerce/frontend` — call in `connectedCallback` with selector `'form'`.
- **Test setup**: `mk-form.test.ts` MUST `import './mk-field'` (and `./mk-input` when fixtures use it) BEFORE `defineAllComponents()` so that `<mk-field>` children in test fixtures are upgraded with the `validate()` method. Without this import, `f.validate()` throws `TypeError: f.validate is not a function`.

## DOM Shape

```html
<mk-form>
  <form method="post" action="/checkout">
    <mk-field>…</mk-field>
    <mk-field>…</mk-field>
    <mk-button><button type="submit">Place Order</button></mk-button>
  </form>
</mk-form>
```

## TypeScript Shape

```typescript
import { MkElement, registerBase, requireInnerControl } from '@markommerce/frontend';
import type { MkFieldElement } from './mk-field';
import '../../css/components/mk-form.css';

export class MkFormElement extends MkElement {
  #form: HTMLFormElement | null = null;
  #submitting = false;

  override connectedCallback(): void {
    this.#form = requireInnerControl(this, 'form') as HTMLFormElement | null;
    if (this.#form) {
      this.#form.setAttribute('novalidate', '');
      this.#form.addEventListener('submit', this.#onSubmit);
    }
    super.connectedCallback();
  }

  override disconnectedCallback(): void {
    this.#form?.removeEventListener('submit', this.#onSubmit);
    super.disconnectedCallback();
  }

  #onSubmit = async (event: SubmitEvent): Promise<void> => {
    event.preventDefault();
    if (this.#submitting) return; // reentrancy guard — ignore double-clicks
    this.#submitting = true;
    try {
      const fields = [...this.querySelectorAll('mk-field')] as MkFieldElement[];
      const results = await Promise.all(fields.map((f) => f.validate()));
      const invalidFields = fields.filter((_, i) => !results[i]);
      if (invalidFields.length > 0) {
        invalidFields[0]!.querySelector<HTMLElement>('input, textarea, select')?.focus();
        this.dispatchEvent(new CustomEvent('mk-invalid', {
          bubbles: true,
          composed: true,
          detail: { fields: invalidFields },
        }));
        return;
      }
      const formData = new FormData(this.#form!);
      this.dispatchEvent(new CustomEvent('mk-submit', {
        bubbles: true,
        composed: true,
        detail: { formData },
      }));
    } finally {
      this.#submitting = false;
    }
  };
}

registerBase('mk-form', MkFormElement);
```

## CSS Shape (`mk-form.css`)

Minimal — `mk-form` is an orchestration wrapper with no visual surface of its own:

```css
@layer components {
  mk-form {
    display: block;
  }
}
```

## Requirements (Test Descriptions)

- [ ] `it registers under the tag name "mk-form"`
- [ ] `it MkFormElement extends MkElement`
- [ ] `it sets novalidate on the inner form element in connectedCallback`
- [ ] `it does not remove its light-DOM form child after upgrade`
- [ ] `it emits mk-submit with FormData detail when all mk-field descendants are valid on form submit`
- [ ] `it prevents native form submission (event.preventDefault) regardless of validation outcome`
- [ ] `it emits mk-invalid with an array of invalid mk-field elements when any field fails validation`
- [ ] `it focuses the first invalid field's inner control when validation fails`
- [ ] `it awaits all mk-field.validate() calls in parallel before dispatching any event`
- [ ] `it removes the submit event listener from the inner form on disconnectedCallback`
- [ ] `it ignores concurrent submit events while a previous submit is still pending (reentrancy guard)`
- [ ] `it the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-form.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- `novalidate` set synchronously in `connectedCallback` before any `submit` event can fire
- `validate()` called via `Promise.all` — all fields validated in parallel
- `mk-submit` and `mk-invalid` events bubble and are composed
- `disconnectedCallback` removes the `submit` listener
- CSS inside `@layer components`
- TypeScript compiles without errors
