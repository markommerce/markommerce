---
title: mk-modal
description: A modal dialog custom element wrapping the native <dialog> element with open/close management and backdrop-click dismissal.
---

`mk-modal` is a light-DOM custom element that wraps a native `<dialog>` to provide an accessible modal overlay. It manages `showModal()` / `close()` calls on the inner dialog in response to the `open` property, handles backdrop-click dismissal when `dismissible` is set, and emits an `mk-close` event when the dialog is closed. Because the dialog is a native HTML element, browser-native focus trapping, scroll locking, and the `::backdrop` pseudo-element all work without any additional JavaScript.

## Installation

`mk-modal` ships with `@markommerce/theme-blank`. Install the npm package:

```bash
npm install @markommerce/theme-blank lit
```

Import the package in your entry point (all components are registered together):

```typescript title="resources/js/main.ts"
import '@markommerce/theme-blank';
```

## Usage

Provide a server-rendered `<dialog>` as the direct child of `<mk-modal>`. The modal is opened by setting `open` to `true` (or calling `openModal()`):

```html
<mk-modal id="confirm-modal" size="sm" dismissible>
  <dialog>
    <h2>Confirm deletion</h2>
    <p>This action cannot be undone.</p>
    <mk-cluster>
      <mk-button variant="danger"><button type="button" id="confirm-delete">Delete</button></mk-button>
      <mk-button><button type="button" id="cancel-delete">Cancel</button></mk-button>
    </mk-cluster>
  </dialog>
</mk-modal>

<script>
  const modal = document.querySelector('#confirm-modal');

  document.querySelector('#open-modal-btn').addEventListener('click', () => {
    modal.open = true;
  });

  document.querySelector('#cancel-delete').addEventListener('click', () => {
    modal.open = false;
  });

  modal.addEventListener('mk-close', () => {
    console.log('Modal closed');
  });
</script>
```

### `openModal()`

For programmatically created modals, use the `openModal()` helper which creates and mounts the `<mk-modal>` element for you:

**Signature:** `openModal(content: HTMLElement | string, options?: ModalOptions): ModalHandle`

```typescript
import { openModal } from '@markommerce/theme-blank';
import type { ModalOptions, ModalHandle } from '@markommerce/theme-blank';

const handle: ModalHandle = openModal('<p>Confirm deletion?</p>', {
  dismissible: true,
  size: 'sm',
});

// Close programmatically:
handle.close();
```

`openModal()` appends an `<mk-modal>` containing a `<dialog>` to `<body>`, sets `open = true`, and removes the element from the DOM when the `mk-close` event fires. The returned `ModalHandle` exposes a single `close()` method.

**`ModalOptions`:**

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `dismissible` | `boolean` | — | Whether clicking the backdrop closes the modal |
| `size` | `'sm' \| 'md' \| 'lg'` | — | Modal width preset |

**`ModalHandle`:**

| Method | Signature | Description |
| --- | --- | --- |
| `close` | `(): void` | Sets `open = false` on the wrapper, triggering the native `close` event |

### Native `<dialog>` behavior

`mk-modal` is a thin orchestration wrapper --- all native browser dialog behavior is preserved:

- **Focus trapping** --- the browser confines tab focus within the dialog while it is open. No JavaScript polyfill is needed.
- **Scroll locking** --- the browser blocks scroll on elements behind the modal backdrop (behavior varies by browser).
- **`::backdrop` pseudo-element** --- styled via `mk-modal > dialog::backdrop` in `@layer components`. Override inside `@layer theme` to customise the backdrop color or blur effect.
- **Escape key** --- the browser fires a `cancel` event on the dialog when Escape is pressed. `mk-modal` calls `event.preventDefault()` on `cancel` events when `dismissible` is `false`, preventing accidental closure via the keyboard. When `dismissible` is `true`, the `cancel` event is allowed to proceed, triggering the `close` event chain.

**Content contract:** The immediate child of `<mk-modal>` must be a `<dialog>` element. Content lives inside `<dialog>`, never as a sibling of `<dialog>` directly under `<mk-modal>`. Sibling content of the dialog is unsupported --- `mk-modal` uses `display: contents`, so any sibling would leak into the parent layout flow.

## API Reference

### Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `open` | `boolean` | `false` | Opens or closes the modal. Setting to `true` calls `dialog.showModal()`; setting to `false` calls `dialog.close()` |
| `size` | `sm \| md \| lg` | — | Width preset applied to the inner `<dialog>` |
| `dismissible` | `boolean` | `false` | Closes the modal on backdrop click or Escape key press |

All attributes are reflected --- setting the property updates the DOM attribute and vice versa.

### Events

| Event | Bubbles | Description |
| --- | --- | --- |
| `mk-close` | Yes | Fired after the inner `<dialog>` emits its native `close` event and `open` is set back to `false` |

### `MkModalElement`

```typescript
const modal = document.querySelector('mk-modal') as MkModalElement;

// Open the modal
modal.open = true;

// Close the modal
modal.open = false;

// Listen for close
modal.addEventListener('mk-close', () => {
  console.log('modal closed');
});
```

`MkModalElement` extends `MkElement` from `@markommerce/frontend`. It inherits `updateComplete` (a `Promise<boolean>` that resolves after Lit's next render cycle) and the mixin chain managed by the component registry.

### CSS tokens

| Token | Purpose |
| --- | --- |
| `--mk-modal-bg` | Dialog background color |
| `--mk-modal-fg` | Dialog foreground (text) color |
| `--mk-modal-radius` | Dialog border radius |
| `--mk-modal-padding` | Dialog inner padding |
| `--mk-modal-shadow` | Dialog box shadow |
| `--mk-modal-width-sm` | Width when `size="sm"` |
| `--mk-modal-width-md` | Width when `size="md"` |
| `--mk-modal-width-lg` | Width when `size="lg"` |
| `--mk-modal-backdrop-color` | `::backdrop` background color |

Override tokens inside `@layer theme` to reskin modals without touching component markup.

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) --- the parent theme package that ships design tokens and layout templates.
- [markommerce/frontend](/docs/packages/frontend/) --- the component registry (`registerBase`, `addMixin`, `defineAllComponents`) and `MkElement` base class.
