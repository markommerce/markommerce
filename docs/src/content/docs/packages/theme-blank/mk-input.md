---
title: mk-input
description: A variant/size text input wrapper that enforces the presence of a light-DOM input element and applies design-token-driven styles.
---

`mk-input` is a custom element wrapper for `<input>` controls. It maps `variant` and `size` attribute values to design-token-driven CSS and delegates the actual input semantics to a slotted `<input>` child in the light DOM. Because the inner `<input>` is authored in HTML, it is server-rendered and accessible before JavaScript loads.

## Installation

`mk-input` ships with `@markommerce/theme-blank`. Install the npm package:

```bash
npm install @markommerce/theme-blank lit
```

Import the package in your entry point (all components are registered together):

```typescript title="resources/js/main.ts"
import '@markommerce/theme-blank';
```

## Usage

Always nest a real `<input>` inside `<mk-input>`. The component wraps and styles it; it does not replace it.

```html
<!-- Default (outline-style border, base size) -->
<mk-input>
  <input type="text" name="email" placeholder="Enter email" />
</mk-input>

<!-- Filled variant, small size -->
<mk-input variant="filled" size="sm">
  <input type="search" name="q" placeholder="Search…" />
</mk-input>

<!-- Large size -->
<mk-input size="lg">
  <input type="text" name="username" placeholder="Username" />
</mk-input>
```

### Variants

The `variant` attribute controls the input's surface style:

| Value | Description |
| --- | --- |
| _(none)_ | Outline style — `--mk-input-bg` background with `--mk-input-border-color` border |
| `filled` | Filled style — `--mk-color-border` background with a transparent border |

### Sizes

The `size` attribute adjusts the input height and font size:

| Value | Height token | Font size token |
| --- | --- | --- |
| `sm` | `--mk-input-height-sm` | `--mk-font-size-sm` |
| `base` (default) | `--mk-input-height-base` | `--mk-font-size-base` |
| `lg` | `--mk-input-height-lg` | `--mk-font-size-lg` |

### States

CSS handles all interactive states via `:has()` on the host element:

- **Focus** — `mk-input:has(input:focus-visible)` applies `--mk-input-border-color-focus` and a `--mk-color-focus-ring` outline.
- **Disabled** — `mk-input:has(input:disabled)` reduces opacity to 0.5.
- **Invalid** — `mk-input:has(input:user-invalid)` applies `--mk-input-border-color-error`.
- **Valid** — `mk-input:has(input:user-valid)` applies `--mk-color-success`.

## API Reference

### Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `variant` | `outline \| filled` | _(none)_ | Visual surface style |
| `size` | `sm \| base \| lg` | _(none — uses base token)_ | Height and font-size preset |

Both attributes are reflected — setting the property updates the DOM attribute and vice versa.

### `MkInputElement`

```typescript
const wrapper = document.querySelector('mk-input') as HTMLElement & {
  variant?: 'outline' | 'filled';
  size?: 'sm' | 'base' | 'lg';
};
wrapper.variant = 'filled';
wrapper.size = 'lg';
```

`MkInputElement` extends `MkElement` from `@markommerce/frontend`. During `connectedCallback` it calls `requireInnerControl(this, 'input')`, which logs a warning if no `<input>` child is found.

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) — the parent theme package that ships design tokens and layout templates.
- [markommerce/frontend](/docs/packages/frontend/) — the component registry (`registerBase`, `addMixin`, `defineAllComponents`), `MkElement` base class, and `requireInnerControl`.
