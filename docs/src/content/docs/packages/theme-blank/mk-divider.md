---
title: mk-divider
description: Styled separator line custom element for Markommerce storefronts --- horizontal or vertical, CSS-only behavior, with automatic ARIA role injection.
---

`mk-divider` is a thin separator line that renders as a single-pixel colored bar using the `--mk-color-border` design token. It supports horizontal (default) and vertical orientations via an `orientation` attribute. The element injects `role="separator"` automatically in `connectedCallback`, so accessibility is built in without any author effort.

## Installation

Install the npm package (peer dependencies `lit` and `open-props` are required):

```bash
npm install @markommerce/theme-blank lit open-props
```

Import the package in your entry point (all 12 primitives are registered together):

```typescript title="resources/js/main.ts"
import '@markommerce/theme-blank';
```

## Usage

Horizontal divider (default):

```html
<mk-divider></mk-divider>
```

Vertical divider inside a flex container:

```html
<div style="display: flex; align-items: center;">
  <span>Left content</span>
  <mk-divider orientation="vertical"></mk-divider>
  <span>Right content</span>
</div>
```

Override the border color via a design token:

```css
@layer theme {
  :root {
    --mk-color-border: var(--gray-5);
  }
}
```

## API Reference

### Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `orientation` | `"horizontal" \| "vertical"` | `"horizontal"` | Controls the direction of the separator line |

### ARIA

The element automatically sets `role="separator"` in `connectedCallback` unless the consumer has already set a `role` attribute. When `orientation="vertical"` is present at connection time, `aria-orientation="vertical"` is also injected.

| Attribute | Value | Condition |
| --- | --- | --- |
| `role` | `"separator"` | Injected if not already present |
| `aria-orientation` | `"vertical"` | Injected when `orientation="vertical"` and not already set |

### CSS Custom Properties

| Property | Default | Description |
| --- | --- | --- |
| `--mk-color-border` | `var(--gray-3)` | Color of the separator bar |
| `--mk-space-3` | `var(--size-3)` | Block margin for horizontal dividers; inline margin for vertical dividers |

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) --- the full theme package that ships `mk-divider` and the complete set of primitive layout components.
- [markommerce/frontend](/docs/packages/frontend/) --- the kernel providing `registerBase`, `addMixin`, `defineAllComponents`, and the `MkElement` base class.
