---
title: mk-select
description: A variant/size select wrapper that enforces the presence of a light-DOM select element and applies design-token-driven styles with a custom arrow.
---

`mk-select` is a custom element wrapper for `<select>` controls. It maps `variant` and `size` attribute values to design-token-driven CSS, removes the browser's native arrow via `appearance: none`, and replaces it with a consistent inline SVG chevron. Because the inner `<select>` is authored in HTML, it is server-rendered and accessible before JavaScript loads.

## Installation

`mk-select` ships with `@markommerce/theme-blank`. Install the npm package:

```bash
npm install @markommerce/theme-blank lit
```

Import the package in your entry point (all components are registered together):

```typescript title="resources/js/main.ts"
import '@markommerce/theme-blank';
```

## Usage

Always nest a real `<select>` inside `<mk-select>`. The component wraps and styles it; it does not replace it.

```html
<!-- Default -->
<mk-select>
  <select name="size">
    <option value="xs">XS</option>
    <option value="sm">S</option>
    <option value="md">M</option>
  </select>
</mk-select>

<!-- Filled variant, small size -->
<mk-select variant="filled" size="sm">
  <select name="color">
    <option value="red">Red</option>
    <option value="blue">Blue</option>
  </select>
</mk-select>

<!-- Large size, outline variant -->
<mk-select variant="outline" size="lg">
  <select name="quantity">
    <option value="1">1</option>
    <option value="2">2</option>
  </select>
</mk-select>
```

### Variants

The `variant` attribute controls the select's surface style:

| Value | Description |
| --- | --- |
| _(none)_ | Default browser-inherited style with design-token overrides |
| `outline` | Explicit outline style with `--mk-input-border-color` border |
| `filled` | Filled style — `--mk-color-border` background with a transparent border |

### Sizes

The `size` attribute adjusts the select height and font size:

| Value | Height token | Font size token |
| --- | --- | --- |
| `sm` | `--mk-input-height-sm` | `--mk-font-size-sm` |
| `base` (default) | `--mk-input-height-base` | `--mk-font-size-base` |
| `lg` | `--mk-input-height-lg` | `--mk-font-size-lg` |

### Custom Arrow

The native select arrow is hidden with `appearance: none` and replaced by an inline SVG chevron set via `background-image`. The arrow is positioned at the inline-end of the control using `background-position: right var(--mk-space-3) center`. Extra `padding-inline-end: var(--mk-space-8)` ensures option text does not overlap the arrow.

### States

CSS handles all interactive states via `:has()` on the host element:

- **Focus** — `mk-select:has(select:focus-visible)` applies `--mk-input-border-color-focus` and a `--mk-color-focus-ring` outline.
- **Disabled** — `mk-select:has(select:disabled)` reduces opacity to 0.5.

## API Reference

### Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `variant` | `outline \| filled` | _(none)_ | Visual surface style |
| `size` | `sm \| base \| lg` | _(none — uses base token)_ | Height and font-size preset |

Both attributes are reflected — setting the property updates the DOM attribute and vice versa.

### `MkSelectElement`

```typescript
const wrapper = document.querySelector('mk-select') as HTMLElement & {
  variant?: 'outline' | 'filled';
  size?: 'sm' | 'base' | 'lg';
};
wrapper.variant = 'filled';
wrapper.size = 'lg';
```

`MkSelectElement` extends `MkElement` from `@markommerce/frontend`. During `connectedCallback` it calls `requireInnerControl(this, 'select')`, which logs a warning if no `<select>` child is found.

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) — the parent theme package that ships design tokens and layout templates.
- [markommerce/frontend](/docs/packages/frontend/) — the component registry (`registerBase`, `addMixin`, `defineAllComponents`), `MkElement` base class, and `requireInnerControl`.
