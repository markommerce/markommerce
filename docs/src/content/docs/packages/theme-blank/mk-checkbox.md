---
title: mk-checkbox
description: A styled checkbox wrapper that tints the native checkbox with the primary brand color via accent-color.
---

`mk-checkbox` is a light-DOM custom element that wraps a native `input[type="checkbox"]`. It uses `accent-color: var(--mk-color-primary)` to tint the browser-native checkbox without replacing it with custom pseudo-element markup. The inner checkbox is server-rendered and visible before JavaScript loads, preserving accessibility and CLS behaviour.

## Installation

`mk-checkbox` ships with `@markommerce/theme-blank`. Install the npm package:

```bash
npm install @markommerce/theme-blank lit
```

Import the package in your entry point (all components are registered together):

```typescript title="resources/js/main.ts"
import '@markommerce/theme-blank';
```

## Usage

Place a native `input[type="checkbox"]` as a direct child of `mk-checkbox`. Any sibling label text or `<label>` element is slotted alongside it automatically:

```html
<mk-checkbox>
  <input type="checkbox" id="terms" name="terms" />
  <label for="terms">I accept the terms and conditions</label>
</mk-checkbox>
```

### Sizes

The `size` attribute adjusts the dimensions of the inner checkbox:

```html
<mk-checkbox size="sm">
  <input type="checkbox" />
  <label>Small</label>
</mk-checkbox>

<mk-checkbox>
  <input type="checkbox" />
  <label>Base (default)</label>
</mk-checkbox>

<mk-checkbox size="lg">
  <input type="checkbox" />
  <label>Large</label>
</mk-checkbox>
```

| Value | Checkbox size |
| --- | --- |
| `sm` | 0.875rem × 0.875rem |
| `base` (default) | 1rem × 1rem |
| `lg` | 1.25rem × 1.25rem |

### Disabled state

Apply `disabled` directly to the inner `<input>`. The `mk-checkbox` wrapper responds with reduced opacity via `:has(input:disabled)`:

```html
<mk-checkbox>
  <input type="checkbox" disabled />
  <label>Disabled option</label>
</mk-checkbox>
```

## API Reference

### Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `size` | `sm \| base \| lg` | — | Size preset affecting the checkbox dimensions |

The `size` attribute is reflected — setting the property updates the DOM attribute and vice versa.

### CSS tokens used

| Token | Usage |
| --- | --- |
| `--mk-color-primary` | `accent-color` for the native checkbox tint |
| `--mk-color-focus-ring` | `outline-color` on `:focus-visible` |
| `--mk-space-2` | Gap between the checkbox and adjacent label text |

### `MkCheckboxElement`

```typescript
const checkbox = document.querySelector('mk-checkbox') as HTMLElement & { size?: string };
checkbox.size = 'lg';
```

`MkCheckboxElement` extends `MkElement` from `@markommerce/frontend`. It inherits `updateComplete` (a `Promise<boolean>` that resolves after Lit's next render cycle). On connection it calls `requireInnerControl(this, 'input[type="checkbox"]')` and throws a loud error if no matching child is found in development.

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) — the parent theme package that ships design tokens and layout templates.
- [markommerce/frontend](/docs/packages/frontend/) — the component registry (`registerBase`, `addMixin`, `defineAllComponents`) and `MkElement` base class.
