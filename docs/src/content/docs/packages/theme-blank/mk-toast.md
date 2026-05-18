---
title: mk-toast
description: A transient notification custom element that auto-dismisses after a configurable duration.
---

`mk-toast` is a light-DOM custom element that renders a transient notification message. It auto-dismisses after a configurable duration, supports semantic `variant` styling, and optionally renders a close button via the `dismissible` attribute. Toasts are created programmatically via `showToast()` and injected into a managed `ol.mk-toast-region` container that is appended to `<body>` on first use.

## Installation

`mk-toast` ships with `@markommerce/theme-blank`. Install the npm package:

```bash
npm install @markommerce/theme-blank lit
```

Import the package in your entry point (all components are registered together):

```typescript title="resources/js/main.ts"
import '@markommerce/theme-blank';
```

## Usage

Create toasts programmatically using the `showToast()` function:

```typescript
import { showToast } from '@markommerce/theme-blank';

showToast('Item added to cart');
showToast('Payment failed', { variant: 'danger', duration: 8000 });
showToast('Profile saved', { variant: 'success' });
```

You can also place `<mk-toast>` directly in HTML if you need server-rendered notifications:

```html
<mk-toast variant="info">Your order is being processed.</mk-toast>
```

When placed in markup, set `duration="0"` to prevent auto-dismiss:

```html
<mk-toast variant="warning" duration="0" dismissible>
  Low stock warning — only 2 items left.
</mk-toast>
```

### `showToast()`

**Signature:** `showToast(message: string, options?: ToastOptions): void`

The function creates and inserts an `<mk-toast>` inside the managed `ol.mk-toast-region` container. If the region does not exist it is created and appended to `<body>`. A maximum of 5 toasts are visible at once --- when a 6th arrives the oldest is removed immediately.

```typescript
import { showToast } from '@markommerce/theme-blank';
import type { ToastOptions } from '@markommerce/theme-blank';

showToast('Item added to cart');
showToast('Payment failed', { variant: 'danger', duration: 5000 });
showToast('Coupon applied', { variant: 'success', duration: 3000 });
```

**`ToastOptions`:**

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `variant` | `'info' \| 'success' \| 'warning' \| 'danger'` | — | Visual style of the toast |
| `duration` | `number` | `5000` | Auto-dismiss delay in milliseconds. Set to `0` to disable auto-dismiss |

### Variants

The `variant` attribute maps to a colored left-border accent:

| Value | Border token | Purpose |
| --- | --- | --- |
| `info` | `--mk-color-info` | General informational message |
| `success` | `--mk-color-success` | Positive confirmation |
| `warning` | `--mk-color-warning` | Cautionary notice |
| `danger` | `--mk-color-danger` | Error or destructive result |

When no `variant` is set the element renders without a colored accent.

## Accessibility

### Role decision: `role="status"` for all variants

`mk-toast` sets `role="status"` on each toast element and `aria-live="polite"` on the `ol.mk-toast-region` container. This applies to **all variants including `danger`**.

The rationale for not using `role="alert"` (which implies `aria-live="assertive"`) on danger toasts:

- Toasts are ephemeral and stack rapidly. A checkout flow or form with multiple validation errors could trigger several danger toasts in quick succession.
- `role="alert"` interrupts the screen reader immediately and re-reads the entire live region on every DOM mutation. Multiple alerts within seconds cause the announcer to cut off mid-sentence and start over, producing an unusable experience.
- `role="status"` with `aria-live="polite"` announces notifications at the next natural pause, queuing messages correctly.

**When to override:** For a genuinely critical, non-repetitive interruption (e.g., "Your session has expired --- you will be logged out in 60 seconds"), override the `role` attribute server-side or at call time:

```html
<!-- Server-rendered critical alert -->
<mk-toast variant="danger" role="alert" duration="0" dismissible>
  Session expiring --- please save your work.
</mk-toast>
```

```typescript
// Programmatic: mutate the element before the region inserts it
// (advanced usage — requires accessing the toast element directly)
const el = document.createElement('mk-toast');
el.setAttribute('role', 'alert');
el.textContent = 'Session expiring';
document.querySelector('ol.mk-toast-region')?.appendChild(el);
```

### Focus management

`mk-toast` sets `tabindex="0"` in `connectedCallback`, making each toast focusable. This allows keyboard users to reach a toast (e.g., via a "skip to notification" link) and read or dismiss it before it auto-removes.

### Auto-dismiss and reduced motion

The auto-dismiss timer runs regardless of `prefers-reduced-motion`. The animation driven by CSS is suppressed under `prefers-reduced-motion: reduce`, but the timer itself is a functional behavior, not a visual one. Consumers who want to disable auto-dismiss under reduced motion should pass `duration: 0` and use the `dismissible` attribute instead.

## API Reference

### Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `variant` | `info \| success \| warning \| danger` | — | Semantic color variant |
| `duration` | `number` | `5000` | Auto-dismiss delay in milliseconds. `0` disables auto-dismiss |
| `dismissible` | `boolean` | `false` | Renders a close button that removes the toast on click |

All attributes are reflected --- setting the property updates the DOM attribute and vice versa.

### `MkToastElement`

```typescript
const toast = document.querySelector('mk-toast') as MkToastElement;

// Set variant
toast.variant = 'success';

// Disable auto-dismiss
toast.duration = 0;
```

`MkToastElement` extends `MkElement` from `@markommerce/frontend`. It inherits `updateComplete` (a `Promise<boolean>` that resolves after Lit's next render cycle) and the mixin chain managed by the component registry.

### CSS tokens

| Token | Purpose |
| --- | --- |
| `--mk-toast-bg` | Background color |
| `--mk-toast-fg` | Foreground (text) color |
| `--mk-toast-padding` | Inner padding |
| `--mk-toast-radius` | Border radius |
| `--mk-toast-shadow` | Box shadow |
| `--mk-toast-min-width` | Minimum width |
| `--mk-toast-max-width` | Maximum width |
| `--mk-toast-region-inset` | Distance from bottom and right viewport edges |
| `--mk-toast-region-gap` | Gap between stacked toasts |

Override tokens inside `@layer theme` to reskin toasts without touching component markup.

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) --- the parent theme package that ships design tokens and layout templates.
- [markommerce/frontend](/docs/packages/frontend/) --- the component registry (`registerBase`, `addMixin`, `defineAllComponents`) and `MkElement` base class.
