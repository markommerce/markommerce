---
title: mk-stack
description: A custom element that stacks children vertically with a consistent gap, driven entirely by CSS via reflected attributes.
---

`mk-stack` is a layout primitive that stacks its children vertically with a configurable gap. It renders as a flex column container and exposes `gap` and `align` attributes that map directly to CSS custom properties and `align-items` values. All visual behavior is CSS-only --- the element reflects attributes to the DOM so that attribute-selector rules in `@layer components` apply without any JavaScript style manipulation.

## HTML Usage

```html
<mk-stack gap="4">
  <p>First item</p>
  <p>Second item</p>
  <p>Third item</p>
</mk-stack>
```

Center-aligned stack with tighter spacing:

```html
<mk-stack gap="2" align="center">
  <img src="avatar.png" alt="User avatar" />
  <span>Jane Doe</span>
</mk-stack>
```

## Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `gap` | `"0"` -- `"9"` | — (uses `--mk-space-3`) | Maps to `gap: var(--mk-space-{n})`. Omit to use the default gap. |
| `align` | `"start" \| "center" \| "end" \| "stretch"` | — (browser default) | Maps to `align-items`. Omit to let children size themselves. |

## Slots

`mk-stack` uses the default (unnamed) slot. All direct children are stacked vertically in source order.

## Events

Emits no events.

## CSS Custom Properties

`mk-stack` consumes the spacing scale from `@markommerce/theme-blank`:

| Property | Purpose |
| --- | --- |
| `--mk-space-0` | Zero gap |
| `--mk-space-1` | Extra-small gap |
| `--mk-space-2` | Small gap |
| `--mk-space-3` | Medium-small gap (default when no `gap` attribute is set) |
| `--mk-space-4` | Base gap |
| `--mk-space-5` | Medium-large gap |
| `--mk-space-6` | Large gap |
| `--mk-space-7` | Extra-large gap |
| `--mk-space-8` | 2x large gap |
| `--mk-space-9` | 3x large gap |

These tokens are defined in `@markommerce/theme-blank/css/tokens.css`. See [markommerce/theme-blank](/docs/packages/theme-blank/) for the full token reference.

## Variants & States

### Gap variants

```html
<!-- No gap -->
<mk-stack gap="0">…</mk-stack>

<!-- Tight -->
<mk-stack gap="1">…</mk-stack>

<!-- Default (medium-small) — no attribute needed -->
<mk-stack>…</mk-stack>

<!-- Roomy -->
<mk-stack gap="6">…</mk-stack>
```

### Alignment variants

```html
<!-- Children hug the start (left) edge -->
<mk-stack align="start">…</mk-stack>

<!-- Children are centered horizontally -->
<mk-stack align="center">…</mk-stack>

<!-- Children hug the end (right) edge -->
<mk-stack align="end">…</mk-stack>

<!-- Children stretch to fill the full width (browser default) -->
<mk-stack align="stretch">…</mk-stack>
```

### Combined

```html
<mk-stack gap="4" align="center">
  <h2>Section Heading</h2>
  <p>Centered content with comfortable spacing.</p>
  <a href="/learn-more">Learn more</a>
</mk-stack>
```

## Extending

Override spacing or add padding inside `@layer theme` so your rules win over the `@layer components` defaults without `!important`:

```css
@layer theme {
  mk-stack {
    padding: var(--mk-space-2);
  }

  mk-stack[gap="4"] {
    gap: var(--mk-space-5); /* bump default gap for this variant */
  }
}
```

## Accessibility

`mk-stack` is a presentational layout element. It carries no ARIA role and exposes no interactive semantics. Screen readers see it as an anonymous container --- its children are announced in DOM order, which is the visual order. Ensure the stacking order in HTML matches the intended reading order.
