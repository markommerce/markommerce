---
title: mk-button
description: A form button custom element with variant, size, and loading state support.
---

`mk-button` is a light-DOM wrapper element for native `<button>` controls. It exposes semantic `variant` and `size` attributes for CSS-token-driven styling, and a `loading` property that disables the inner button and sets `aria-busy` to prevent double-submission. Because it wraps a real `<button>`, all native form semantics (submit, reset, disabled, focus) work out of the box.

## Installation

`mk-button` ships with `@markommerce/theme-blank`. Install the npm package:

```bash
npm install @markommerce/theme-blank lit
```

Import the package in your entry point (all primitives are registered together):

```typescript title="resources/js/main.ts"
import '@markommerce/theme-blank';
```

## Usage

Wrap a native `<button>` element with `<mk-button>`:

```html
<mk-button>
  <button type="submit">Place order</button>
</mk-button>

<mk-button variant="primary">
  <button type="submit">Add to cart</button>
</mk-button>

<mk-button variant="danger" size="sm">
  <button type="button">Remove</button>
</mk-button>

<mk-button loading>
  <button type="submit" disabled>Processing…</button>
</mk-button>
```

### Variants

The `variant` attribute maps to semantic color tokens applied to the inner button:

| Value | Background token | Text token | Border |
| --- | --- | --- | --- |
| (none) | transparent | `--mk-color-fg` | `--mk-color-border` |
| `primary` | `--mk-color-primary` | `--mk-color-on-primary` | none |
| `secondary` | transparent | `--mk-color-fg` | `--mk-color-border` |
| `ghost` | none | `--mk-color-fg` | none |
| `danger` | `--mk-color-danger` | `--mk-color-on-primary` | none |

### Sizes

The `size` attribute adjusts padding and font size:

| Value | Description |
| --- | --- |
| `sm` | Smaller padding and font size |
| `base` (default) | Standard padding and font size |
| `lg` | Larger padding and font size |

### Loading state

Setting `loading` to `true` on the element:

1. Sets `disabled` on the inner `<button>` element.
2. Sets `aria-busy="true"` on the `<mk-button>` wrapper.

When `loading` returns to `false`, both attributes are removed.

```html
<mk-button id="submit-btn">
  <button type="submit">Place order</button>
</mk-button>

<script>
  document.querySelector('#submit-btn').loading = true;
</script>
```

## API Reference

### Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `variant` | `primary \| secondary \| ghost \| danger` | — | Semantic color variant |
| `size` | `sm \| base \| lg` | — | Size preset affecting padding and font size |
| `loading` | `boolean` | `false` | Disables the inner button and sets `aria-busy="true"` on the wrapper |

All attributes are reflected — setting the property updates the DOM attribute and vice versa.

### `MkButtonElement`

```typescript
const btn = document.querySelector('mk-button') as MkButtonElement;

// Set variant
btn.variant = 'primary';

// Set size
btn.size = 'lg';

// Trigger loading state
btn.loading = true;
// → inner <button> becomes disabled, wrapper gets aria-busy="true"

btn.loading = false;
// → inner <button> re-enabled, aria-busy removed
```

`MkButtonElement` extends `MkElement` from `@markommerce/frontend`. It inherits `updateComplete` (a `Promise<boolean>` that resolves after Lit's next render cycle) and the mixin chain managed by the component registry.

### CSS states

| Selector | Effect |
| --- | --- |
| `:has(button:disabled)` | `opacity: 0.5`, `cursor: not-allowed` |
| `:has(button:focus-visible)` | Focus ring using `--mk-color-focus-ring` |

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) — the parent theme package that ships design tokens and layout templates.
- [markommerce/frontend](/docs/packages/frontend/) — the component registry (`registerBase`, `addMixin`, `defineAllComponents`) and `MkElement` base class.
