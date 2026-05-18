---
title: mk-field
description: A validation orchestrator custom element that wraps a native form control and coordinates sync and async validators.
---

`mk-field` is a light-DOM wrapper element that turns a plain form control (`<input>`, `<textarea>`, or `<select>`) into a fully validated field. It coordinates built-in browser validation (Constraint Validation API), custom synchronous validators, and debounced asynchronous validators. Error messages are surfaced into a `[data-mk-error]` child element and `data-state` reflects the field's current validity state for CSS-driven styling.

## Installation

`mk-field` ships with `@markommerce/theme-blank`. Install the npm package:

```bash
npm install @markommerce/theme-blank lit
```

Import the package in your entry point (all primitives are registered together):

```typescript title="resources/js/main.ts"
import '@markommerce/theme-blank';
```

## Usage

Wrap a native form control with `<mk-field>`, add a `<label>`, and include a `<small data-mk-error>` element where validation messages will appear:

```html
<mk-field>
  <label for="email">Email</label>
  <mk-input>
    <input id="email" name="email" type="email" required>
  </mk-input>
  <small data-mk-error></small>
</mk-field>
```

### With a textarea

```html
<mk-field>
  <label for="message">Message</label>
  <mk-textarea>
    <textarea id="message" name="message" required></textarea>
  </mk-textarea>
  <small data-mk-error></small>
</mk-field>
```

### With a select

```html
<mk-field>
  <label for="country">Country</label>
  <mk-select>
    <select id="country" name="country" required>
      <option value="">Choose…</option>
      <option value="us">United States</option>
    </select>
  </mk-select>
  <small data-mk-error></small>
</mk-field>
```

### Adding a custom sync validator

```typescript
const field = document.querySelector('mk-field[data-field="email"]') as MkFieldElement;

field.addValidator('min-length', (value) => {
  if (value.length < 5) return 'Must be at least 5 characters.';
  return null;
});
```

### Adding an async validator

```typescript
field.addAsyncValidator('email-taken', async (value) => {
  const res = await fetch(`/api/check-email?email=${encodeURIComponent(value)}`);
  const { taken } = await res.json();
  return taken ? 'This email is already in use.' : null;
});
```

### Triggering validation programmatically

```typescript
const isValid = await field.validate();
if (!isValid) {
  console.log('Field is invalid');
}
```

### Data states

`mk-field` sets `data-state` on the host element to reflect validation state:

| Value | Meaning |
| --- | --- |
| `pristine` | Untouched or error cleared |
| `valid` | Passed all validators |
| `invalid` | Failed validation — error is shown |
| `validating` | Async validator in progress |

Use these states in CSS:

```css
mk-field[data-state="invalid"] label {
  color: var(--mk-color-danger);
}
```

## API Reference

### DOM Structure

```html
<mk-field>
  <label for="field-id">Label text</label>
  <!-- any mk-input / mk-textarea / mk-select wrapper, or bare control -->
  <small data-mk-error></small>
  <!-- optional hint slot -->
  <small slot="hint">Hint text</small>
</mk-field>
```

### Methods

#### `addValidator(name: string, fn: SyncValidator): void`

Registers a synchronous validator. If a validator with the same `name` already exists, it is overwritten.

```typescript
type SyncValidator = (value: string, form: FormData | null) => string | null;
```

Return a non-null string to signal failure; return `null` to pass.

#### `addAsyncValidator(name: string, fn: AsyncValidator): void`

Registers an asynchronous validator. Async validators are debounced by 300 ms when triggered by blur/input events. They run immediately (without debounce) when `validate()` is called directly.

```typescript
type AsyncValidator = (value: string, form: FormData | null) => Promise<string | null>;
```

#### `validate(): Promise<boolean>`

Runs all validators in order (sync first, then async) and returns `true` if the field is valid, `false` otherwise. Any pending debounce timer is cancelled before running.

### Attributes

| Attribute | Set by | Meaning |
| --- | --- | --- |
| `data-state` | Component | Current state: `pristine`, `valid`, `invalid`, or `validating` |
| `data-touched` | Component | Present once the control has been blurred at least once; never removed (sticky) |

### CSS variables

| Variable | Usage |
| --- | --- |
| `--mk-field-gap` | Gap between label, control, hint, and error elements |
| `--mk-field-error-color` | Color of the error message text |
| `--mk-field-hint-color` | Color of the hint text |
| `--mk-font-size-sm` | Font size for error and hint text |

### `MkFieldElement`

`MkFieldElement` extends `MkElement` from `@markommerce/frontend`. It inherits `updateComplete` (a `Promise<boolean>` that resolves after Lit's next render cycle).

```typescript
import { MkFieldElement } from '@markommerce/theme-blank';

const field = document.querySelector('mk-field') as MkFieldElement;

field.addValidator('required-custom', (value) =>
  value.trim() === '' ? 'This field is required.' : null
);

const ok = await field.validate();
```

### Validation flow

1. On **blur**: sync validators run immediately; async validators are debounced by 300 ms.
2. On **input** (after an invalid state): error is cleared and state resets to `pristine`.
3. On **`validate()` call**: pending debounce is cancelled, all validators run sequentially (sync, then async), then `checkValidity()` is called to fire the `invalid` event and populate `validationMessage`.
4. On **`invalid` event** from the control: the error message is surfaced into `[data-mk-error]`.

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) — the parent theme package that ships design tokens and layout templates.
- [markommerce/frontend](/docs/packages/frontend/) — the component registry (`registerBase`, `addMixin`, `defineAllComponents`) and `MkElement` base class.
- [mk-input](/docs/packages/theme-blank/mk-input/) — the recommended inner wrapper for `<input>` elements.
- [mk-textarea](/docs/packages/theme-blank/mk-textarea/) — the recommended inner wrapper for `<textarea>` elements.
