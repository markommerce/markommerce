---
title: mk-text
description: A single-tag body-text primitive with semantic visual variants and weight overrides.
---

`mk-text` is a CSS-driven custom element for rendering body text. It maps semantic `variant` values to typography design tokens and supports optional `weight` overrides. The element uses light DOM so its text content is server-rendered and visible before JavaScript loads. Both single-tag and two-tag (legacy) forms are supported.

## Installation

`mk-text` ships with `@markommerce/theme-blank`. Install the npm package:

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
<mk-text>Default body text</mk-text>
<mk-text variant="lead">Introductory lead paragraph</mk-text>
<mk-text variant="small">Fine print or helper text</mk-text>
<mk-text variant="muted">Secondary or de-emphasized text</mk-text>
<mk-text variant="body" weight="bold">Bold body text</mk-text>
```

### Two-Tag Form

When a direct child `<p>`, `<span>`, or `<div>` is present, `mk-text` resets its own host styles and inherits them down to the child. This allows use in legacy templates where an inner element already carries the content:

```html
<mk-text variant="lead"><p>Introductory paragraph in a two-tag form</p></mk-text>
```

### Variants

The `variant` attribute controls font size, line height, and color:

| Value | Font size token | Line height token | Color token |
| --- | --- | --- | --- |
| `body` (default) | `--mk-font-size-base` | `--mk-line-height-normal` | `--mk-color-fg` |
| `lead` | `--mk-font-size-lg` | `--mk-line-height-loose` | `--mk-color-fg` |
| `small` | `--mk-font-size-sm` | `--mk-line-height-normal` | `--mk-color-fg` |
| `muted` | `--mk-font-size-base` | `--mk-line-height-normal` | `--mk-color-fg-muted` |

### Weights

The `weight` attribute overrides the font weight independently of the variant:

| Value | Font weight token |
| --- | --- |
| `normal` (default) | `--mk-font-weight-normal` |
| `medium` | `--mk-font-weight-medium` |
| `bold` | `--mk-font-weight-bold` |

## API Reference

### Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `variant` | `body \| lead \| small \| muted` | `body` | Semantic typography variant |
| `weight` | `normal \| medium \| bold` | `normal` | Font weight override |

Both attributes are reflected --- setting the property updates the DOM attribute and vice versa.

### `MkTextElement`

```typescript
const text = document.querySelector('mk-text') as HTMLElement & { variant?: string; weight?: string };
text.variant = 'lead';
text.weight = 'medium';
```

`MkTextElement` extends `MkElement` from `@markommerce/frontend`. It inherits `updateComplete` (a `Promise<boolean>` that resolves after Lit's next render cycle) and the mixin chain managed by the component registry.

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) --- the parent theme package that ships design tokens and layout templates.
- [markommerce/frontend](/docs/packages/frontend/) --- the component registry (`registerBase`, `addMixin`, `defineAllComponents`) and `MkElement` base class.
