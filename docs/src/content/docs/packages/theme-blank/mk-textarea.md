---
title: mk-textarea
description: A variant/size textarea wrapper custom element for multi-line text input with outline and filled styles.
---

`mk-textarea` is a CSS-driven custom element that wraps a native `<textarea>` control. It maps `variant` values to design-token border and background styles and offers three `size` presets for vertical height. The element uses light DOM so the inner `<textarea>` is server-rendered and accessible before JavaScript loads.

## Installation

`mk-textarea` ships with `@markommerce/theme-blank`. Install the npm package:

```bash
npm install @markommerce/theme-blank lit
```

Import the package in your entry point (all components are registered together):

```typescript title="resources/js/main.ts"
import '@markommerce/theme-blank';
```

## Usage

Place a native `<textarea>` inside the custom element:

```html
<mk-textarea>
  <textarea name="message" rows="4"></textarea>
</mk-textarea>

<mk-textarea variant="outline">
  <textarea name="description" placeholder="Enter a description…"></textarea>
</mk-textarea>

<mk-textarea variant="filled" size="lg">
  <textarea name="notes"></textarea>
</mk-textarea>
```

### Variants

The `variant` attribute controls the border and background treatment:

| Value | Description |
| --- | --- |
| `outline` (default) | Bordered textarea with transparent background |
| `filled` | Filled background with no visible border |

### Sizes

The `size` attribute adjusts the minimum height and padding:

| Value | Min-height token | Padding token |
| --- | --- | --- |
| `sm` | `--mk-input-height-sm` | `--mk-space-2` / `--mk-input-padding-inline` |
| `base` (default) | `--mk-input-height-base` | `--mk-space-2` / `--mk-input-padding-inline` |
| `lg` | `--mk-input-height-lg` | `--mk-space-2` / `--mk-input-padding-inline` |

## API Reference

### Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `variant` | `outline \| filled` | `outline` | Visual treatment of the textarea wrapper |
| `size` | `sm \| base \| lg` | `base` | Size preset affecting min-height and padding |

Both attributes are reflected — setting the property updates the DOM attribute and vice versa.

### `MkTextareaElement`

```typescript
const ta = document.querySelector('mk-textarea') as HTMLElement & {
  variant?: string;
  size?: string;
};
ta.variant = 'filled';
ta.size = 'sm';
```

`MkTextareaElement` extends `MkElement` from `@markommerce/frontend`. It inherits `updateComplete` (a `Promise<boolean>` that resolves after Lit's next render cycle) and calls `requireInnerControl(this, 'textarea')` in `connectedCallback` to warn in development when no native `<textarea>` child is present.

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) — the parent theme package that ships design tokens and layout templates.
- [markommerce/frontend](/docs/packages/frontend/) — the component registry (`registerBase`, `addMixin`, `defineAllComponents`) and `MkElement` base class.
