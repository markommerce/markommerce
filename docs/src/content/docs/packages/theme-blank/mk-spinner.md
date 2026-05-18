---
title: mk-spinner
description: A CSS-animated loading spinner custom element with size variants and built-in screen reader support.
---

`mk-spinner` is a light-DOM custom element that renders a CSS-animated circular loading indicator. It automatically injects a visually-hidden "Loading…" text label for screen readers and sets `role="status"` and `aria-live="polite"` on `connectedCallback`. The spinner is driven entirely by CSS, so it renders correctly before JavaScript loads --- zero CLS by design.

## Installation

`mk-spinner` ships with `@markommerce/theme-blank`. Install the npm package:

```bash
npm install @markommerce/theme-blank lit
```

Import the package in your entry point (all components are registered together):

```typescript title="resources/js/main.ts"
import '@markommerce/theme-blank';
```

## Usage

Place the element anywhere a loading state is needed:

```html
<!-- Default size -->
<mk-spinner></mk-spinner>

<!-- With explicit size -->
<mk-spinner size="sm"></mk-spinner>
<mk-spinner size="lg"></mk-spinner>

<!-- Custom accessible label -->
<mk-spinner>
  <span class="mk-visually-hidden">Loading products…</span>
</mk-spinner>
```

### Sizes

The `size` attribute adjusts the spinner's diameter:

| Value | Token | Description |
| --- | --- | --- |
| `sm` | `--mk-spinner-size-sm` | Small spinner for inline contexts |
| `base` (default) | `--mk-spinner-size-base` | Standard spinner |
| `lg` | `--mk-spinner-size-lg` | Large spinner for full-section loading states |

## Accessibility

`mk-spinner` follows the WAI-ARIA spinner pattern:

- **`role="status"`** is set in `connectedCallback` if the attribute is not already present. `role="status"` implies `aria-live="polite"`, announcing the element at the next natural pause without interrupting ongoing speech.
- **`aria-live="polite"`** is also set explicitly on the element for broader AT compatibility.
- **Visually-hidden label** --- if no text node or `span.mk-visually-hidden` child is present when `connectedCallback` runs, the element injects `<span class="mk-visually-hidden">Loading…</span>` automatically.

To provide a more descriptive label, supply your own `span.mk-visually-hidden` child:

```html
<mk-spinner>
  <span class="mk-visually-hidden">Loading search results…</span>
</mk-spinner>
```

The automatic label injection is skipped when a `span.mk-visually-hidden` child or any text node is already present.

### Reduced motion

The spin animation is suppressed under `prefers-reduced-motion: reduce`. The element still renders (as a static ring) so the loading state remains visible --- only the rotation animation is disabled.

## API Reference

### Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `size` | `sm \| base \| lg` | — | Size preset controlling the spinner diameter |

The `size` attribute is reflected --- setting the property updates the DOM attribute and vice versa.

### `MkSpinnerElement`

```typescript
const spinner = document.querySelector('mk-spinner') as MkSpinnerElement;

// Set size
spinner.size = 'lg';
```

`MkSpinnerElement` extends `MkElement` from `@markommerce/frontend`. It inherits `updateComplete` (a `Promise<boolean>` that resolves after Lit's next render cycle) and the mixin chain managed by the component registry.

### CSS tokens

| Token | Purpose |
| --- | --- |
| `--mk-spinner-size-sm` | Diameter of the small variant |
| `--mk-spinner-size-base` | Diameter of the default variant |
| `--mk-spinner-size-lg` | Diameter of the large variant |
| `--mk-spinner-thickness` | Border width of the spinner ring |
| `--mk-spinner-color` | Color of the animated arc (top-border color) |
| `--mk-spinner-duration` | Duration of one full rotation |

Override tokens inside `@layer theme` to reskin the spinner without touching component markup.

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) --- the parent theme package that ships design tokens and layout templates.
- [markommerce/frontend](/docs/packages/frontend/) --- the component registry (`registerBase`, `addMixin`, `defineAllComponents`) and `MkElement` base class.
