---
title: mk-cover
description: Full-height vertical frame with optional header and footer regions and a centered main area.
---

`mk-cover` is a CSS Grid-based layout primitive that pins an optional header to the top and an optional footer to the bottom of a full-height frame, while centering all remaining children in the middle track. It is the standard building block for hero sections, landing pages, and any view that must fill the viewport.

## Usage

Place children directly inside `<mk-cover>`. Mark the top region with `class="mk-cover-header"` and the bottom region with `class="mk-cover-footer"`. All other direct children are placed in the centered `1fr` middle track.

```html
<mk-cover min-height="100vh">
  <header class="mk-cover-header">
    <nav>Site navigation</nav>
  </header>

  <main>
    <h1>Welcome to the store</h1>
    <p>Browse our latest products.</p>
  </main>

  <footer class="mk-cover-footer">
    <p>© 2026 My Store</p>
  </footer>
</mk-cover>
```

## Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `min-height` | CSS length | `100vh` | Minimum height of the cover frame. Synced to `--mk-cover-min-height` as an inline style during `connectedCallback`. |

## CSS Custom Properties

| Property | Default | Description |
| --- | --- | --- |
| `--mk-cover-min-height` | `100vh` | Controls `min-height`. Set via the `min-height` attribute or overridden directly in CSS. |

## Semantic Structure

Direct children are placed in one of three grid rows based on their CSS class:

| Child selector | Grid row | Purpose |
| --- | --- | --- |
| `.mk-cover-header` | Row 1 (`auto`) | Pinned to the top |
| All other children | Row 2 (`1fr`) | Centered in the available space |
| `.mk-cover-footer` | Row 3 (`auto`) | Pinned to the bottom |

The grid uses `grid-template-rows: auto 1fr auto`. The `1fr` track expands to fill the remaining height, and children in that track receive `align-self: center`.

## CLS Prevention

The `min-height` attribute is read synchronously inside `connectedCallback` before Lit's asynchronous update cycle runs. This means the `--mk-cover-min-height` custom property is written as an inline style on the element immediately --- before the first paint --- preventing any Cumulative Layout Shift caused by a deferred style update.

## CSS Shape

```css title="resources/css/components/mk-cover.css"
@layer components {
  mk-cover {
    display: grid;
    min-height: var(--mk-cover-min-height, 100vh);
    grid-template-rows: auto 1fr auto;
    gap: var(--mk-space-4);
    padding: var(--mk-space-4);
  }

  mk-cover > :not(.mk-cover-header):not(.mk-cover-footer) {
    align-self: center;
    grid-row: 2;
  }

  mk-cover > .mk-cover-header {
    grid-row: 1;
  }

  mk-cover > .mk-cover-footer {
    grid-row: 3;
  }
}
```

## Related Packages

- [markommerce/frontend](/docs/packages/frontend/) --- provides `MkElement`, `registerBase`, and `defineAllComponents`.
- [markommerce/theme-blank](/docs/packages/theme-blank/) --- the package that ships `mk-cover` alongside all other layout primitives.
