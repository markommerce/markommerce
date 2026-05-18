---
title: mk-alert
description: An inline alert banner custom element with semantic variant styles and optional dismissibility.
---

`mk-alert` is a light-DOM custom element that renders an inline alert banner. It maps semantic `variant` values to design-token colors and supports an optional close button via the `dismissible` attribute. The element uses light DOM so its content is server-rendered and visible before JavaScript loads --- zero CLS by design.

## Installation

`mk-alert` ships with `@markommerce/theme-blank`. Install the npm package:

```bash
npm install @markommerce/theme-blank lit
```

Import the package in your entry point (all components are registered together):

```typescript title="resources/js/main.ts"
import '@markommerce/theme-blank';
```

## Usage

Use the custom element directly in HTML or Latte templates:

```html
<mk-alert variant="info">
  <p>Your order has been received and is being processed.</p>
</mk-alert>

<mk-alert variant="success">
  <p>Payment confirmed. Thank you for your purchase!</p>
</mk-alert>

<mk-alert variant="warning">
  <p>Only 2 items left in stock.</p>
</mk-alert>

<mk-alert variant="danger">
  <p>Your session has expired. Please sign in again.</p>
</mk-alert>
```

### Variants

The `variant` attribute maps to semantic color tokens applied to background, foreground, and border:

| Value | Background token | Foreground token | Border token |
| --- | --- | --- | --- |
| `info` | `--mk-alert-bg-info` | `--mk-alert-fg-info` | `--mk-alert-border-color-info` |
| `success` | `--mk-alert-bg-success` | `--mk-alert-fg-success` | `--mk-alert-border-color-success` |
| `warning` | `--mk-alert-bg-warning` | `--mk-alert-fg-warning` | `--mk-alert-border-color-warning` |
| `danger` | `--mk-alert-bg-danger` | `--mk-alert-fg-danger` | `--mk-alert-border-color-danger` |

When no `variant` is set the element renders with default surface colors.

### Dismissible

Add the `dismissible` attribute to render a close button. When clicked, the element removes itself from the DOM immediately (no animation).

```html
<mk-alert variant="info" dismissible>
  <p>This notice can be dismissed.</p>
</mk-alert>
```

The close button is injected by JavaScript in `connectedCallback`. It carries `class="mk-alert-close"` and `aria-label="Dismiss"`. Server-rendered markup must not include a second close button --- `connectedCallback` checks for an existing `button.mk-alert-close` before injecting.

To position and style the close button, target it from CSS inside `@layer theme`:

```css
@layer theme {
  mk-alert[dismissible] {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: start;
  }

  .mk-alert-close {
    /* custom close button styles */
  }
}
```

## API Reference

### Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `variant` | `info \| success \| warning \| danger` | — | Semantic color variant |
| `dismissible` | `boolean` | `false` | Renders a close button that removes the element on click |

The `variant` attribute is reflected --- setting the property updates the DOM attribute and vice versa.

### `MkAlertElement`

```typescript
const alert = document.querySelector('mk-alert') as MkAlertElement;

// Set variant
alert.variant = 'success';

// Make dismissible at runtime (close button is injected by connectedCallback,
// so set the attribute before appending to the DOM for server-rendered markup)
alert.setAttribute('dismissible', '');
```

`MkAlertElement` extends `MkElement` from `@markommerce/frontend`. It inherits `updateComplete` (a `Promise<boolean>` that resolves after Lit's next render cycle) and the mixin chain managed by the component registry.

### CSS tokens

| Token | Purpose |
| --- | --- |
| `--mk-alert-bg-{variant}` | Background color per variant |
| `--mk-alert-fg-{variant}` | Foreground (text) color per variant |
| `--mk-alert-border-color-{variant}` | Border color per variant |
| `--mk-alert-radius` | Border radius |

The component CSS uses `var(--mk-space-4)` for padding directly rather than a dedicated `--mk-alert-padding` token. Override alert padding inside `@layer theme` by targeting `mk-alert` directly:

```css
@layer theme {
  mk-alert {
    padding: var(--mk-space-3);
  }
}
```

Override tokens inside `@layer theme` to reskin alerts without touching component markup.

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) --- the parent theme package that ships design tokens and layout templates.
- [markommerce/frontend](/docs/packages/frontend/) --- the component registry (`registerBase`, `addMixin`, `defineAllComponents`) and `MkElement` base class.
