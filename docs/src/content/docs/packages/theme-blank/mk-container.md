---
title: mk-container
description: A max-width content container custom element with auto inline margins and a configurable size attribute.
---

`mk-container` is a layout primitive that constrains content to a maximum width and centers it horizontally. It renders as a block element with `margin-inline: auto` and `padding-inline: var(--mk-space-4)`, and exposes a `size` attribute that maps to discrete max-width presets via attribute-selector rules in `@layer components`. All visual behavior is CSS-only --- no JavaScript style manipulation.

## HTML Usage

```html
<mk-container>
  <p>Centered content at the default large (80rem) max-width.</p>
</mk-container>
```

Narrow container for prose or forms:

```html
<mk-container size="sm">
  <form>…</form>
</mk-container>
```

Full-bleed container (removes max-width):

```html
<mk-container size="full">
  <div class="hero-banner">…</div>
</mk-container>
```

## Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `size` | `"sm" \| "md" \| "lg" \| "xl" \| "full"` | `"lg"` (80rem) | Controls the max-width of the container. `"full"` removes the max-width entirely. |

### Size presets

| Value | Max-width |
| --- | --- |
| `sm` | `40rem` |
| `md` | `60rem` |
| `lg` | `80rem` (default) |
| `xl` | `96rem` |
| `full` | none |

## Slots

`mk-container` uses the default (unnamed) slot. All direct children are placed inside the constrained-width block.

## Events

Emits no events.

## CSS Custom Properties

`mk-container` consumes the spacing token for its default inline padding:

| Property | Purpose |
| --- | --- |
| `--mk-space-4` | Default inline padding on both sides |

This token is defined in `@markommerce/theme-blank/css/tokens.css`. See [markommerce/theme-blank](/docs/packages/theme-blank/) for the full token reference.

## Variants

### Size variants

```html
<!-- Narrow — ideal for prose, login forms, or narrow dialogs -->
<mk-container size="sm">…</mk-container>

<!-- Medium — useful for article content or settings pages -->
<mk-container size="md">…</mk-container>

<!-- Large — default, general-purpose page content area -->
<mk-container size="lg">…</mk-container>
<!-- or simply: -->
<mk-container>…</mk-container>

<!-- Extra-large — wide dashboards or data tables -->
<mk-container size="xl">…</mk-container>

<!-- Full-bleed — hero banners, full-width sections -->
<mk-container size="full">…</mk-container>
```

## Extending

Override max-width values or padding inside `@layer theme` so your rules win over the `@layer components` defaults without `!important`:

```css
@layer theme {
  mk-container {
    padding-inline: var(--mk-space-6); /* wider gutters */
  }

  mk-container[size="lg"] {
    max-width: 72rem; /* narrower default */
  }
}
```

## Accessibility

`mk-container` is a presentational layout element. It carries no ARIA role and exposes no interactive semantics. Screen readers see it as an anonymous block container. Ensure content placed inside follows its own accessibility requirements.
