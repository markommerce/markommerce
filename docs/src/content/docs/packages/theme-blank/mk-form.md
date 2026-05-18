---
title: mk-form
description: A form orchestrator custom element that validates all child mk-field descendants before submitting.
---

`mk-form` is a light-DOM orchestration wrapper for native `<form>` elements. It intercepts form submission, runs `validate()` on every `<mk-field>` descendant in parallel, and either emits `mk-submit` (with `FormData`) on success or `mk-invalid` (with the list of failed fields) on failure. Native browser validation is disabled via `novalidate` so all validation goes through the `mk-field` pipeline.

## Installation

`mk-form` ships with `@markommerce/theme-blank`. Install the npm package:

```bash
npm install @markommerce/theme-blank
```

Import the package in your entry point (all components are registered together):

```typescript title="resources/js/main.ts"
import '@markommerce/theme-blank';
```

## Usage

Wrap a native `<form>` element with `<mk-form>` and place `<mk-field>` children inside the form:

```html
<mk-form>
  <form method="post" action="/checkout">
    <mk-field>
      <label for="name">Name</label>
      <input id="name" type="text" name="name" required />
      <small data-mk-error></small>
    </mk-field>

    <mk-field>
      <label for="email">Email</label>
      <input id="email" type="email" name="email" required />
      <small data-mk-error></small>
    </mk-field>

    <mk-button>
      <button type="submit">Place Order</button>
    </mk-button>
  </form>
</mk-form>
```

### Listening for events

```javascript
const form = document.querySelector('mk-form');

form.addEventListener('mk-submit', (event) => {
  const { formData } = event.detail;
  // send formData to your server
  fetch('/checkout', { method: 'POST', body: formData });
});

form.addEventListener('mk-invalid', (event) => {
  const { fields } = event.detail;
  console.log('Invalid fields:', fields);
});
```

### How validation works

On form submission `mk-form`:

1. Calls `preventDefault()` on the native submit event to suppress browser navigation.
2. Collects all `<mk-field>` descendants using `querySelectorAll('mk-field')`.
3. Calls `validate()` on all fields **in parallel** using `Promise.all`.
4. If any field is invalid, focuses the first invalid field's inner control and dispatches `mk-invalid`.
5. If all fields are valid, collects `FormData` from the inner `<form>` and dispatches `mk-submit`.

### Reentrancy guard

While a submission is in progress (validators are still running), any additional submit events are ignored. The guard resets automatically once the promise chain settles.

## API Reference

### Events

| Event | Detail | Description |
| --- | --- | --- |
| `mk-submit` | `{ formData: FormData }` | Fired when all fields pass validation. `formData` contains all form field values. |
| `mk-invalid` | `{ fields: MkFieldElement[] }` | Fired when one or more fields fail validation. `fields` is the array of invalid `<mk-field>` elements. |

Both events bubble and are `composed: true`, so they propagate through shadow DOM boundaries.

### `MkFormElement`

```typescript
const form = document.querySelector('mk-form') as MkFormElement;
```

`MkFormElement` extends `MkElement` from `@markommerce/frontend`. It inherits `updateComplete` (a `Promise<boolean>` that resolves after the next render cycle) and the mixin chain managed by the component registry.

### CSS

`mk-form` renders as `display: block`. All visual styling is delegated to the inner `<form>` and child components.

## Related Packages

- [mk-field](/docs/packages/theme-blank/mk-field/) — the field wrapper that exposes the `validate()` method consumed by `mk-form`.
- [mk-button](/docs/packages/theme-blank/mk-button/) — button wrapper with loading-state support, typically used as the submit trigger inside `mk-form`.
- [markommerce/theme-blank](/docs/packages/theme-blank/) — the parent theme package that ships design tokens and layout templates.
- [markommerce/frontend](/docs/packages/frontend/) — the component registry and `MkElement` base class.
