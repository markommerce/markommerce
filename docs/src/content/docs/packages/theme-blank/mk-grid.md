---
title: mk-grid
description: Responsive auto-fit CSS Grid container that lays children in equal columns that shrink and wrap automatically based on a minimum column width.
---

`mk-grid` is a layout primitive that creates a responsive auto-fit CSS Grid container. Children are laid out in equal columns that shrink and wrap automatically based on a minimum column width, using `grid-template-columns: repeat(auto-fit, minmax(min(var(--mk-grid-min, 16rem), 100%), 1fr))` to avoid overflow on narrow viewports.

## Installation

`mk-grid` is included in `@markommerce/theme-blank`. All 12 primitives are registered together when the main entry is imported:

```typescript title="resources/js/main.ts"
import '@markommerce/theme-blank';
```

## Usage

```html
<mk-grid>
  <div>Item 1</div>
  <div>Item 2</div>
  <div>Item 3</div>
</mk-grid>
```

Set a custom minimum column width:

```html
<mk-grid min="12rem">
  <div>Item 1</div>
  <div>Item 2</div>
</mk-grid>
```

Control the gap between items:

```html
<mk-grid gap="4">
  <div>Item 1</div>
  <div>Item 2</div>
</mk-grid>
```

## API Reference

### Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `min` | `string` | `16rem` | Minimum column width before wrapping. Accepts any CSS length value (e.g., `"12rem"`, `"200px"`). Sets `--mk-grid-min` as an inline CSS custom property on the element. |
| `gap` | `0`–`9` | `3` | Gap between grid items. Maps to `--mk-space-{n}` from the design token scale. |

### CSS Custom Properties

| Property | Default | Description |
| --- | --- | --- |
| `--mk-grid-min` | `16rem` | Minimum column width used in the `minmax()` track sizing. Set automatically from the `min` attribute. Override directly in CSS for static values. |

## CSS Shape

`mk-grid` styles live inside `@layer components`:

```css
@layer components {
  mk-grid {
    display: grid;
    gap: var(--mk-space-3);
    grid-template-columns: repeat(auto-fit, minmax(min(var(--mk-grid-min, 16rem), 100%), 1fr));
  }
  mk-grid[gap="0"] { gap: var(--mk-space-0); }
  mk-grid[gap="1"] { gap: var(--mk-space-1); }
  mk-grid[gap="2"] { gap: var(--mk-space-2); }
  mk-grid[gap="3"] { gap: var(--mk-space-3); }
  mk-grid[gap="4"] { gap: var(--mk-space-4); }
  mk-grid[gap="5"] { gap: var(--mk-space-5); }
  mk-grid[gap="6"] { gap: var(--mk-space-6); }
  mk-grid[gap="7"] { gap: var(--mk-space-7); }
  mk-grid[gap="8"] { gap: var(--mk-space-8); }
  mk-grid[gap="9"] { gap: var(--mk-space-9); }
}
```

The `min(var(--mk-grid-min, 16rem), 100%)` expression ensures columns never overflow their container on narrow viewports --- the column width is capped at `100%` when the viewport is smaller than the minimum.

## CLS Prevention

`mk-grid` is a CSS-only layout primitive. The Lit class layer exists only to manage the `--mk-grid-min` inline custom property when the `min` attribute is set dynamically via JavaScript.

To avoid Cumulative Layout Shift (CLS), the `--mk-grid-min` property is set synchronously inside `connectedCallback()`, before `super.connectedCallback()` runs. This means the inline style is present immediately when the element upgrades --- no microtask delay, no layout shift between the server-rendered state and the JS-enhanced state.

When the `min` attribute is present in server-rendered HTML, the browser applies it before any JavaScript executes, so the grid columns are sized correctly on first paint.

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) --- the full theme package that ships `mk-grid` alongside all other layout primitives.
- [markommerce/frontend](/docs/packages/frontend/) --- the kernel providing `MkElement`, `registerBase`, and `defineAllComponents`.
