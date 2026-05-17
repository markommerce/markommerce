---
title: mk-cluster
description: A custom element that arranges children in a horizontal flex row with wrap, driven entirely by CSS via reflected attributes.
---

`mk-cluster` is a layout primitive for horizontal, wrapping flex rows --- ideal for button rows, tag lists, inline metadata, or any set of items that should sit side by side and wrap gracefully when space runs out. It exposes `gap`, `align`, and `justify` attributes that map directly to CSS `gap`, `align-items`, and `justify-content` values. All visual behavior is CSS-only --- the element reflects attributes to the DOM so that attribute-selector rules in `@layer components` apply without any JavaScript style manipulation.

## HTML Usage

A row of three labeled badges:

```html
<mk-cluster gap="3">
  <span class="badge">New</span>
  <span class="badge">Sale</span>
  <span class="badge">Featured</span>
</mk-cluster>
```

A button row aligned to the end:

```html
<mk-cluster justify="end" gap="2">
  <button type="button">Cancel</button>
  <button type="submit">Save</button>
</mk-cluster>
```

Centered inline metadata:

```html
<mk-cluster align="center" gap="3">
  <img src="avatar.png" alt="User avatar" width="32" height="32" />
  <span>Jane Doe</span>
  <span>Administrator</span>
</mk-cluster>
```

## Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `gap` | `"0"` -- `"9"` | — (uses `--mk-space-3`) | Maps to `gap: var(--mk-space-{n})`. Omit to use the default gap. |
| `align` | `"start" \| "center" \| "end" \| "baseline" \| "stretch"` | — (`center`) | Maps to `align-items`. |
| `justify` | `"start" \| "center" \| "end" \| "between" \| "around" \| "evenly"` | — (`start`) | Maps to `justify-content`. |

## Slots

`mk-cluster` uses the default (unnamed) slot. All direct children are arranged horizontally in source order and wrap to a new line when the container is full.

## Events

Emits no events.

## CSS Custom Properties

`mk-cluster` consumes the spacing scale from `@markommerce/theme-blank`:

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
<mk-cluster gap="0">…</mk-cluster>

<!-- Tight -->
<mk-cluster gap="1">…</mk-cluster>

<!-- Default (medium-small) — no attribute needed -->
<mk-cluster>…</mk-cluster>

<!-- Roomy -->
<mk-cluster gap="6">…</mk-cluster>
```

### Alignment variants

```html
<!-- Items align to the top edge -->
<mk-cluster align="start">…</mk-cluster>

<!-- Items align to the vertical center (default) -->
<mk-cluster align="center">…</mk-cluster>

<!-- Items align to the bottom edge -->
<mk-cluster align="end">…</mk-cluster>

<!-- Items align on their text baseline -->
<mk-cluster align="baseline">…</mk-cluster>

<!-- Items stretch to fill the cross-axis height -->
<mk-cluster align="stretch">…</mk-cluster>
```

### Justify variants

```html
<!-- Items pack to the start (default) -->
<mk-cluster justify="start">…</mk-cluster>

<!-- Items center along the main axis -->
<mk-cluster justify="center">…</mk-cluster>

<!-- Items pack to the end -->
<mk-cluster justify="end">…</mk-cluster>

<!-- Items spread with space between them -->
<mk-cluster justify="between">…</mk-cluster>

<!-- Items spread with space around them -->
<mk-cluster justify="around">…</mk-cluster>

<!-- Items spread with equal space between and around them -->
<mk-cluster justify="evenly">…</mk-cluster>
```

### Combined

```html
<mk-cluster gap="3" align="center" justify="between">
  <span class="badge badge--success">In Stock</span>
  <span class="badge badge--info">Free Shipping</span>
  <span class="badge badge--warning">Limited</span>
</mk-cluster>
```

## Extending

Override spacing or add padding inside `@layer theme` so your rules win over the `@layer components` defaults without `!important`:

```css
@layer theme {
  mk-cluster {
    padding: var(--mk-space-2);
  }

  mk-cluster[gap="4"] {
    gap: var(--mk-space-5); /* bump default gap for this variant */
  }
}
```

## Accessibility

`mk-cluster` is a presentational layout element. It carries no ARIA role and exposes no interactive semantics. Screen readers see it as an anonymous container --- its children are announced in DOM order, which is the visual order. Ensure the cluster order in HTML matches the intended reading order.
