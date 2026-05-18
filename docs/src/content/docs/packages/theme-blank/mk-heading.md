---
title: mk-heading
description: Single-tag heading primitive with decoupled semantic level and visual size, plus automatic ARIA injection.
---

`mk-heading` is a custom element that decouples a heading's semantic level from its visual size. It auto-injects `role="heading"` and `aria-level` during `connectedCallback` so headings are accessible without requiring a native `<h1>`–`<h6>` wrapper. Both single-tag (preferred) and two-tag (legacy/SEO) forms are supported.

## Installation

Install the npm package:

```bash
npm install @markommerce/theme-blank lit
```

All 12 primitives are registered when the main entry is imported. Load the package via the `markommerceModuleScanner` Vite plugin, or import it manually in your entry point:

```typescript title="resources/js/main.ts"
import '@markommerce/theme-blank';
```

## Usage

### Single-tag form (preferred)

```html
<mk-heading level="1" size="3xl">Welcome to our store</mk-heading>
<mk-heading level="2" size="lg">About us</mk-heading>
<mk-heading level="3" size="base" weight="medium">Our team</mk-heading>
```

In the single-tag form, `mk-heading` automatically receives `role="heading"` and `aria-level` matching the `level` attribute value during `connectedCallback`. Screen readers treat it as a native heading.

### Two-tag form (legacy/SEO)

```html
<mk-heading size="lg"><h2>SEO-critical heading</h2></mk-heading>
```

When a direct child `<h1>`–`<h6>` element is present, `mk-heading` does not inject `role` or `aria-level`. The inner native heading element carries the semantics. Use this form when server-rendered SEO requires a real heading tag in the DOM.

### Attribute-driven visual styles

```html
<!-- Extra-large bold heading (defaults) -->
<mk-heading level="2">Product title</mk-heading>

<!-- Small, normal weight -->
<mk-heading level="3" size="sm" weight="normal">Section note</mk-heading>

<!-- 3xl for hero text -->
<mk-heading level="1" size="3xl">Hero banner</mk-heading>
```

## API Reference

### Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `level` | `1 \| 2 \| 3 \| 4 \| 5 \| 6` | — | Semantic heading level. Synced to `aria-level` (single-tag form only). |
| `size` | `xs \| sm \| base \| lg \| xl \| 2xl \| 3xl` | `xl` | Visual font size. Maps to the `--mk-font-size-*` token scale. |
| `weight` | `normal \| medium \| bold` | `bold` | Font weight. Maps to the `--mk-font-weight-*` token scale. |

All three attributes reflect between the JS property and the DOM attribute.

### ARIA injection rules

- If no direct child `<h1>`–`<h6>` is present, `connectedCallback` sets `role="heading"` (unless already set) and `aria-level` matching `level` (unless already set).
- If a direct child heading element is present, no ARIA attributes are injected --- semantics are provided by the inner element.
- Consumer-supplied `role` or `aria-level` attributes are never overwritten.

### CSS tokens used

| Token | Purpose |
| --- | --- |
| `--mk-font-size-xs` through `--mk-font-size-3xl` | Font size scale mapped by the `size` attribute |
| `--mk-font-weight-normal`, `--mk-font-weight-medium`, `--mk-font-weight-bold` | Font weight mapped by the `weight` attribute |
| `--mk-line-height-tight` | Default line height for heading text |

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) --- the parent package that ships `mk-heading` alongside design tokens, layouts, and other primitives.
- [markommerce/frontend](/docs/packages/frontend/) --- the kernel providing `MkElement`, `registerBase`, and `defineAllComponents`.
