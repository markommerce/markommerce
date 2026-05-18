---
title: mk-skeleton
description: A CSS-animated shimmer placeholder custom element for loading states.
---

`mk-skeleton` is a light-DOM custom element that renders an animated shimmer placeholder during loading states. It supports `text`, `circle`, and `rect` shape variants for matching the geometry of the content it replaces, and automatically sets `aria-hidden="true"` so screen readers skip the decorative placeholder entirely.

## Installation

`mk-skeleton` ships with `@markommerce/theme-blank`. Install the npm package:

```bash
npm install @markommerce/theme-blank lit
```

Import the package in your entry point (all components are registered together):

```typescript title="resources/js/main.ts"
import '@markommerce/theme-blank';
```

## Usage

Use `mk-skeleton` to match the shape and size of the content being loaded:

```html
<!-- Rectangular block (default) -->
<mk-skeleton></mk-skeleton>

<!-- Text line placeholder -->
<mk-skeleton variant="text" style="width: 60%"></mk-skeleton>

<!-- Avatar placeholder -->
<mk-skeleton variant="circle" style="width: 3rem; height: 3rem"></mk-skeleton>

<!-- Card image placeholder -->
<mk-skeleton variant="rect" style="height: 200px"></mk-skeleton>
```

Combine multiple skeletons to build a skeleton screen that mirrors the real layout:

```html
<mk-stack>
  <mk-skeleton variant="rect" style="height: 200px"></mk-skeleton>
  <mk-skeleton variant="text" style="width: 80%"></mk-skeleton>
  <mk-skeleton variant="text" style="width: 50%"></mk-skeleton>
</mk-stack>
```

### Variants

The `variant` attribute controls the shape of the placeholder:

| Value | Shape | Default dimensions |
| --- | --- | --- |
| `text` | Short inline rectangle | `height: 1em`, `min-width: 4em` |
| `circle` | Circle | `height: 2em`, `width: 2em` |
| `rect` | Full-width rectangle | `height: 6em`, `width: 100%` |
| (none) | Same as `rect` | `height: 6em`, `width: 100%` |

Override the default dimensions with inline styles or `@layer theme` rules.

## Accessibility

`mk-skeleton` sets `aria-hidden="true"` in `connectedCallback` if the attribute is not already present. This hides the decorative shimmer from screen readers entirely. The placeholder communicates no semantic information --- the real accessible content replaces it once loaded.

Pair skeleton screens with a live region announcement to notify screen reader users that content has loaded:

```html
<!-- Shown during loading -->
<mk-skeleton variant="rect" style="height: 300px"></mk-skeleton>

<!-- Announce completion via a polite live region -->
<div role="status" aria-live="polite" class="mk-visually-hidden" id="load-status"></div>

<script>
  // After content loads and skeleton is removed:
  document.getElementById('load-status').textContent = 'Products loaded.';
</script>
```

### Reduced motion

The shimmer animation is suppressed under `prefers-reduced-motion: reduce`. The element still renders as a static muted block so the loading placeholder remains visible --- only the animation is disabled.

## API Reference

### Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `variant` | `text \| circle \| rect` | — | Shape preset for the skeleton placeholder |

The `variant` attribute is reflected --- setting the property updates the DOM attribute and vice versa.

### `MkSkeletonElement`

```typescript
const skeleton = document.querySelector('mk-skeleton') as MkSkeletonElement;

// Set variant
skeleton.variant = 'circle';
```

`MkSkeletonElement` extends `MkElement` from `@markommerce/frontend`. It inherits `updateComplete` (a `Promise<boolean>` that resolves after Lit's next render cycle) and the mixin chain managed by the component registry.

### CSS tokens

| Token | Purpose |
| --- | --- |
| `--mk-skeleton-bg` | Base shimmer background color |
| `--mk-skeleton-shimmer-color` | Highlight color of the shimmer sweep |
| `--mk-skeleton-duration` | Duration of one shimmer cycle |
| `--mk-skeleton-radius` | Border radius for `text` and `rect` variants |

Override tokens inside `@layer theme` to reskin the skeleton without touching component markup.

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) --- the parent theme package that ships design tokens and layout templates.
- [markommerce/frontend](/docs/packages/frontend/) --- the component registry (`registerBase`, `addMixin`, `defineAllComponents`) and `MkElement` base class.
