---
title: mk-badge
description: A small inline-block badge for status, counts, or labels with semantic color variants.
---

`mk-badge` is a CSS-only custom element that renders a compact inline badge. It maps semantic `variant` values to design-token colors and offers three `size` presets. The element uses light DOM so its text content is server-rendered and visible before JavaScript loads.

## Installation

`mk-badge` ships with `@markommerce/theme-blank`. Install the npm package:

```bash
npm install @markommerce/theme-blank lit
```

Import the package in your entry point (all 12 primitives are registered together):

```typescript title="resources/js/main.ts"
import '@markommerce/theme-blank';
```

## Usage

Use the custom element directly in HTML or Latte templates:

```html
<mk-badge>Default</mk-badge>
<mk-badge variant="success">Active</mk-badge>
<mk-badge variant="danger" size="sm">Out of stock</mk-badge>
<mk-badge variant="info" size="lg">New</mk-badge>
```

### Variants

The `variant` attribute maps to semantic color tokens:

| Value | Background token | Text token |
| --- | --- | --- |
| `neutral` (default) | `--mk-color-border` | `--mk-color-fg` |
| `primary` | `--mk-color-primary` | `--mk-color-on-primary` |
| `success` | `--mk-color-success` | `--mk-color-on-primary` |
| `warning` | `--mk-color-warning` | `--mk-color-on-primary` |
| `danger` | `--mk-color-danger` | `--mk-color-on-primary` |
| `info` | `--mk-color-info` | `--mk-color-on-primary` |

### Sizes

The `size` attribute adjusts font size and horizontal padding:

| Value | Font size token | Padding-inline token |
| --- | --- | --- |
| `sm` | `--mk-font-size-xs` | `--mk-space-1` |
| `base` (default) | `--mk-font-size-sm` | `--mk-space-2` |
| `lg` | `--mk-font-size-base` | `--mk-space-3` |

## API Reference

### Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `variant` | `neutral \| primary \| success \| warning \| danger \| info` | `neutral` | Semantic color variant |
| `size` | `sm \| base \| lg` | `base` | Size preset affecting font size and padding |

Both attributes are reflected --- setting the property updates the DOM attribute and vice versa.

### `MkBadgeElement`

```typescript
const badge = document.querySelector('mk-badge') as HTMLElement & { variant?: string; size?: string };
badge.variant = 'success';
badge.size = 'sm';
```

`MkBadgeElement` extends `MkElement` from `@markommerce/frontend`. It inherits `updateComplete` (a `Promise<boolean>` that resolves after Lit's next render cycle) and the mixin chain managed by the component registry.

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) --- the parent theme package that ships design tokens and layout templates.
- [markommerce/frontend](/docs/packages/frontend/) --- the component registry (`registerBase`, `addMixin`, `defineAllComponents`) and `MkElement` base class.
