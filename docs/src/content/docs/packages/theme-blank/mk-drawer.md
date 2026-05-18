---
title: mk-drawer
description: A slide-in drawer panel custom element wrapping the native <dialog> element with placement and dismissal support.
---

`mk-drawer` is a light-DOM custom element that wraps a native `<dialog>` to produce a slide-in drawer panel. It manages `showModal()` / `close()` calls on the inner dialog in response to the `open` property, supports `left` and `right` placement via the `placement` attribute, and handles backdrop-click dismissal when `dismissible` is set. Because the drawer relies on the native `<dialog>`, browser-native focus trapping and the `::backdrop` pseudo-element work without any additional JavaScript.

## Installation

`mk-drawer` ships with `@markommerce/theme-blank`. Install the npm package:

```bash
npm install @markommerce/theme-blank lit
```

Import the package in your entry point (all components are registered together):

```typescript title="resources/js/main.ts"
import '@markommerce/theme-blank';
```

## Usage

Provide a server-rendered `<dialog>` as the direct child of `<mk-drawer>`. Open the drawer by setting `open` to `true` (or via `openDrawer()`):

```html
<mk-drawer id="cart-drawer" placement="right" size="md" dismissible>
  <dialog>
    <h2>Your cart</h2>
    <p>No items yet.</p>
  </dialog>
</mk-drawer>

<script>
  const drawer = document.querySelector('#cart-drawer');

  document.querySelector('#open-cart-btn').addEventListener('click', () => {
    drawer.open = true;
  });

  drawer.addEventListener('mk-close', () => {
    console.log('Drawer closed');
  });
</script>
```

### `openDrawer()`

For programmatically created drawers, use the `openDrawer()` helper which creates and mounts the `<mk-drawer>` element for you:

**Signature:** `openDrawer(content: HTMLElement | string, options?: DrawerOptions): DrawerHandle`

```typescript
import { openDrawer } from '@markommerce/theme-blank';
import type { DrawerOptions, DrawerHandle } from '@markommerce/theme-blank';

const handle: DrawerHandle = openDrawer('<p>Filter options…</p>', {
  placement: 'left',
  size: 'md',
  dismissible: true,
});

// Close programmatically:
handle.close();
```

`openDrawer()` appends an `<mk-drawer>` containing a `<dialog>` to `<body>`, sets the `open` attribute, and removes the element from the DOM when the `mk-close` event fires. The returned `DrawerHandle` exposes a single `close()` method.

**`DrawerOptions`:**

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `placement` | `'left' \| 'right'` | `'right'` | Side of the viewport the drawer slides in from |
| `size` | `'sm' \| 'md' \| 'lg'` | — | Drawer width preset |
| `dismissible` | `boolean` | — | Whether clicking the backdrop closes the drawer |

**`DrawerHandle`:**

| Method | Signature | Description |
| --- | --- | --- |
| `close` | `(): void` | Removes the `open` attribute on the wrapper, triggering the native `close` event |

### Placement

The `placement` attribute controls which side of the viewport the drawer panel slides in from:

| Value | Effect |
| --- | --- |
| `right` (default) | Panel slides in from the right edge |
| `left` | Panel slides in from the left edge |

If no `placement` attribute is present when the element connects, `connectedCallback` sets `placement="right"` automatically.

## API Reference

### Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `open` | `boolean` | `false` | Opens or closes the drawer. Setting to `true` calls `dialog.showModal()`; setting to `false` calls `dialog.close()` |
| `placement` | `left \| right` | `right` | Side the drawer panel appears from |
| `size` | `sm \| md \| lg` | — | Width preset applied to the inner `<dialog>` |
| `dismissible` | `boolean` | `false` | Closes the drawer on backdrop click or Escape key press |

All attributes are reflected --- setting the property updates the DOM attribute and vice versa.

### Events

| Event | Bubbles | Description |
| --- | --- | --- |
| `mk-close` | Yes | Fired when the inner `<dialog>` emits its native `close` event and `open` is set back to `false` |

### `MkDrawerElement`

```typescript
const drawer = document.querySelector('mk-drawer') as MkDrawerElement;

// Open from the right (default)
drawer.open = true;

// Change placement
drawer.placement = 'left';

// Close
drawer.open = false;

// Listen for close
drawer.addEventListener('mk-close', () => {
  console.log('drawer closed');
});
```

`MkDrawerElement` extends `MkElement` from `@markommerce/frontend`. It inherits `updateComplete` (a `Promise<boolean>` that resolves after Lit's next render cycle) and the mixin chain managed by the component registry.

### CSS tokens

| Token | Purpose |
| --- | --- |
| `--mk-drawer-bg` | Drawer background color |
| `--mk-drawer-fg` | Drawer foreground (text) color |
| `--mk-drawer-padding` | Drawer inner padding |
| `--mk-drawer-shadow` | Drawer box shadow |
| `--mk-drawer-width-sm` | Width when `size="sm"` |
| `--mk-drawer-width-md` | Width when `size="md"` (also the default) |
| `--mk-drawer-width-lg` | Width when `size="lg"` |

Override tokens inside `@layer theme` to reskin drawers without touching component markup.

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) --- the parent theme package that ships design tokens and layout templates.
- [markommerce/frontend](/docs/packages/frontend/) --- the component registry (`registerBase`, `addMixin`, `defineAllComponents`) and `MkElement` base class.
