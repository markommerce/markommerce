---
title: mk-radio
description: A styled radio button wrapper custom element with size support.
---

`mk-radio` is a light-DOM wrapper element for native `<input type="radio">` controls. It exposes a semantic `size` attribute for CSS-token-driven styling via `accent-color`. Because it wraps a real `<input type="radio">`, all native form semantics (name, value, checked, required) work out of the box.

## Installation

`mk-radio` ships with `@markommerce/theme-blank`. Install the npm package:

```bash
npm install @markommerce/theme-blank lit
```

Import the package in your entry point (all primitives are registered together):

```typescript title="resources/js/main.ts"
import '@markommerce/theme-blank';
```

## Usage

Wrap a native `<input type="radio">` element with `<mk-radio>`. Consumers **must** specify `type="radio"` explicitly:

```html
<mk-radio>
  <input type="radio" name="color" value="red" />
</mk-radio>

<mk-radio size="lg">
  <input type="radio" name="color" value="blue" />
</mk-radio>

<mk-radio size="sm">
  <input type="radio" name="color" value="green" checked />
</mk-radio>
```

### Sizes

The `size` attribute adjusts the rendered size of the radio control:

| Value | Description |
| --- | --- |
| `sm` | Smaller radio control |
| `base` (default) | Standard radio control size |
| `lg` | Larger radio control |

Size is applied via CSS custom properties and `accent-color` tinting using the `--mk-color-primary` token.

## API Reference

### Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `size` | `sm \| base \| lg` | — | Size preset affecting the visual size of the radio control |

The attribute is reflected — setting the property updates the DOM attribute and vice versa.

### `MkRadioElement`

```typescript
const radio = document.querySelector('mk-radio') as MkRadioElement;

// Set size
radio.size = 'lg';

// Read the inner input
const input = radio.querySelector('input[type="radio"]');
```

`MkRadioElement` extends `MkElement` from `@markommerce/frontend`. It inherits `updateComplete` (a `Promise<boolean>` that resolves after Lit's next render cycle) and the mixin chain managed by the component registry.

If no `<input type="radio">` child is found when the element connects, a console warning is emitted to aid development.

### CSS customization

All styles live inside `@layer components` and use only `--mk-*` design tokens:

| Token used | Purpose |
| --- | --- |
| `--mk-color-primary` | `accent-color` tint for the checked state |
| `--mk-space-*` | Size-variant dimensions |

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) — the parent theme package that ships design tokens and layout templates.
- [markommerce/frontend](/docs/packages/frontend/) — the component registry (`registerBase`, `addMixin`, `defineAllComponents`) and `MkElement` base class.
