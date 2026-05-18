---
title: mk-fieldset
description: A group wrapper custom element for related form fields, resetting native fieldset styles and applying design-token spacing and typography.
---

`mk-fieldset` is a CSS-driven custom element that wraps a native `<fieldset>` control. It resets all browser-default fieldset borders, padding, and margin, then applies design-token gap between fields and a consistently styled legend. The element uses light DOM so the inner `<fieldset>` and its `<legend>` are server-rendered and accessible before JavaScript loads.

## Installation

`mk-fieldset` ships with `@markommerce/theme-blank`. Install the npm package:

```bash
npm install @markommerce/theme-blank lit
```

Import the package in your entry point (all components are registered together):

```typescript title="resources/js/main.ts"
import '@markommerce/theme-blank';
```

## Usage

Place a native `<fieldset>` (optionally with a `<legend>`) inside the custom element:

```html
<mk-fieldset>
  <fieldset>
    <legend>Shipping Address</legend>
    <mk-input>
      <input type="text" name="street" placeholder="Street address" />
    </mk-input>
    <mk-input>
      <input type="text" name="city" placeholder="City" />
    </mk-input>
  </fieldset>
</mk-fieldset>
```

Without a legend:

```html
<mk-fieldset>
  <fieldset>
    <mk-checkbox>
      <input type="checkbox" name="terms" />
    </mk-checkbox>
    <mk-checkbox>
      <input type="checkbox" name="newsletter" />
    </mk-checkbox>
  </fieldset>
</mk-fieldset>
```

## API Reference

### Required Child

`mk-fieldset` requires a direct `<fieldset>` child. In development mode, `requireInnerControl` emits a console warning if no `<fieldset>` is found after the element connects.

### CSS Tokens Used

| Token | Applied to | Purpose |
| --- | --- | --- |
| `--mk-field-gap` | `fieldset` | Vertical gap between field children |
| `--mk-font-weight-medium` | `legend` | Legend font weight |
| `--mk-font-size-base` | `legend` | Legend font size |
| `--mk-space-2` | `legend` | Bottom margin below legend |

### `MkFieldsetElement`

```typescript
const fs = document.querySelector('mk-fieldset') as HTMLElement;
// The element itself has no public attributes beyond standard HTMLElement.
```

`MkFieldsetElement` extends `MkElement` from `@markommerce/frontend`. It inherits `updateComplete` (a `Promise<boolean>` that resolves after Lit's next render cycle) and calls `requireInnerControl(this, 'fieldset')` in `connectedCallback` to warn in development when no native `<fieldset>` child is present.

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) — the parent theme package that ships design tokens and layout templates.
- [markommerce/frontend](/docs/packages/frontend/) — the component registry (`registerBase`, `addMixin`, `defineAllComponents`) and `MkElement` base class.
