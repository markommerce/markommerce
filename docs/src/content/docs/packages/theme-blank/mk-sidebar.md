---
title: mk-sidebar
description: A two-child layout primitive that places a fixed-width sidebar adjacent to a flex-growing main content area.
---

`mk-sidebar` is a CSS-only layout primitive from `@markommerce/theme-blank` that implements the Every Layout sidebar pattern. It uses pure flexbox --- no container queries needed. The first child is always the sidebar when `side="left"` (the default); the second child grows to fill the remaining space. When the sidebar would be narrower than 50% of the container, both children wrap to a single-column stacked layout automatically.

## Usage

```html
<mk-sidebar>
  <nav>Category navigation</nav>
  <main>Page content</main>
</mk-sidebar>
```

Place exactly two children inside `mk-sidebar`. The first child becomes the sidebar; the second child becomes the main content area.

## Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `side` | `left \| right` | `left` | Which side the sidebar occupies |
| `width` | CSS length | `15rem` | Fixed width of the sidebar, synced to `--mk-sidebar-width` |

### `side`

Controls which child is the sidebar. With `side="left"` (default), the first child is the fixed-width sidebar. With `side="right"`, the last child becomes the sidebar and the first child grows to fill the available space.

```html
<!-- Sidebar on the right -->
<mk-sidebar side="right">
  <main>Page content</main>
  <aside>Related links</aside>
</mk-sidebar>
```

### `width`

Sets the sidebar width as any valid CSS length. The value is applied as the `--mk-sidebar-width` custom property on the element's inline style, which feeds the `flex-basis` of the sidebar child.

```html
<mk-sidebar width="20rem">
  <nav>Navigation</nav>
  <main>Content</main>
</mk-sidebar>
```

## CSS Custom Properties

| Property | Default | Description |
| --- | --- | --- |
| `--mk-sidebar-width` | `15rem` | Width of the sidebar child |

Override `--mk-sidebar-width` via the `width` attribute or directly in CSS:

```css
@layer theme {
  mk-sidebar.product-layout {
    --mk-sidebar-width: 18rem;
  }
}
```

## CLS Prevention

`mk-sidebar` writes `--mk-sidebar-width` to the element's inline style synchronously inside `connectedCallback`, before Lit's reactive update cycle runs. This means the correct sidebar width is applied on the element's first paint --- no layout shift occurs between the server-rendered state and the JavaScript-enhanced state.

When the `width` attribute is already present in the HTML (as rendered by the PHP/Latte template), the inline style is set immediately when the element connects to the DOM.

## Layout Behavior

The sidebar pattern uses flexbox with `flex-wrap: wrap`. When the container is wide enough, the sidebar and main content sit side by side. When the container narrows below the threshold where the main content area would be less than 50% of the container width, both children wrap to a full-width stacked layout.

The main content child uses `flex-grow: 999` and `min-inline-size: 50%` to ensure it always expands to fill remaining space and wraps cleanly when needed.

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) --- the parent package that ships `mk-sidebar` alongside all other layout primitives.
- [markommerce/frontend](/docs/packages/frontend/) --- the kernel providing `registerBase`, `MkElement`, and `defineAllComponents`.
