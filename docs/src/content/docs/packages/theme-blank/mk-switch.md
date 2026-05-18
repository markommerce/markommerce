---
title: mk-switch
description: A toggle-switch custom element wrapping a native checkbox with CSS-driven pill animation and automatic ARIA role injection.
---

`mk-switch` is a light-DOM wrapper element for native `<input type="checkbox">` controls styled as a toggle pill. It injects `role="switch"` on the inner input during `connectedCallback` (unless already set by the server), and exposes a `size` attribute for CSS-token-driven sizing. Because it wraps a real checkbox, all native form semantics (checked state, disabled, focus, labels) work out of the box.

## Installation

`mk-switch` ships with `@markommerce/theme-blank`. Install the npm package:

```bash
npm install @markommerce/theme-blank lit
```

Import the package in your entry point (all primitives are registered together):

```typescript title="resources/js/main.ts"
import '@markommerce/theme-blank';
```

## Usage

Wrap a native `<input type="checkbox">` element with `<mk-switch>`:

```html
<mk-switch>
  <input type="checkbox">
</mk-switch>
```

### With a label

Pair `mk-switch` inside an `<mk-field>` for a labeled toggle:

```html
<mk-field>
  <label for="notifications">Enable notifications</label>
  <mk-switch>
    <input type="checkbox" id="notifications">
  </mk-switch>
</mk-field>
```

### Sizes

```html
<mk-switch size="sm">
  <input type="checkbox">
</mk-switch>

<mk-switch>
  <input type="checkbox">
</mk-switch>

<mk-switch size="lg">
  <input type="checkbox">
</mk-switch>
```

### Disabled state

```html
<mk-switch>
  <input type="checkbox" disabled>
</mk-switch>
```

When the inner checkbox is disabled, the wrapper automatically becomes semi-transparent (`opacity: 0.5`) and shows `cursor: not-allowed`.

### Pre-checked state

```html
<mk-switch>
  <input type="checkbox" checked>
</mk-switch>
```

### Server-rendered role override

If a server-rendered ARIA role is already set on the input, `mk-switch` will not overwrite it:

```html
<mk-switch>
  <input type="checkbox" role="checkbox">
</mk-switch>
```

## API Reference

### Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `size` | `sm \| base \| lg` | — | Size preset affecting the toggle pill dimensions |

The `size` attribute reflects between the JS property and the DOM attribute.

### ARIA injection rules

During `connectedCallback`, `mk-switch` finds the inner `input[type="checkbox"]` and sets `role="switch"` on it, unless the `role` attribute is already present. This makes the toggle accessible to screen readers as a switch control without requiring extra markup.

### CSS tokens used

| Token | Purpose |
| --- | --- |
| `--mk-space-2` | Gap between the toggle pill and any sibling label text |
| `--mk-radius-full` | Pill and thumb border radius |
| `--mk-color-border` | Background color of the unchecked pill |
| `--mk-color-primary` | Background color of the checked pill |
| `--mk-color-focus-ring` | Focus ring color on keyboard focus |
| `--mk-transition-fast` | Transition duration for color and translate animations |

### CSS states

| Selector | Effect |
| --- | --- |
| `:checked` | Pill background changes to `--mk-color-primary` |
| `:checked::before` | Thumb translates 1 rem to the right |
| `:has(input:focus-visible)` | Focus ring outline using `--mk-color-focus-ring` |
| `:has(input:disabled)` | `opacity: 0.5`, `cursor: not-allowed` |

### `MkSwitchElement`

```typescript
const sw = document.querySelector('mk-switch') as MkSwitchElement;

// Set size
sw.size = 'lg';

// Read the inner checkbox state
const input = sw.querySelector('input[type="checkbox"]') as HTMLInputElement;
console.log(input.checked);
```

`MkSwitchElement` extends `MkElement` from `@markommerce/frontend`. It inherits `updateComplete` (a `Promise<boolean>` that resolves after Lit's next render cycle).

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) — the parent theme package that ships design tokens and layout templates.
- [markommerce/frontend](/docs/packages/frontend/) — the component registry (`registerBase`, `defineAllComponents`) and `MkElement` base class.
