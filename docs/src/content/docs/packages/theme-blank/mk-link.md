---
title: mk-link
description: Styled wrapper for native anchor elements with variant colors and underline-display policies.
---

`mk-link` is a CSS-only custom element that wraps a native `<a>` tag and applies variant colors and underline-display policies. The consumer provides the `<a>` element as light-DOM content; `mk-link` styles it via attribute selectors inside `@layer components`.

## Usage

```html
<mk-link variant="muted" underline="hover">
  <a href="/about">About</a>
</mk-link>
```

## Attributes

| Attribute | Values | Default | Description |
| --- | --- | --- | --- |
| `variant` | `default \| muted \| danger` | `default` | Controls the link color via design tokens |
| `underline` | `always \| hover \| never` | `hover` | Controls when the underline is visible |

### `variant`

- `default` --- uses `--mk-color-primary` (brand color)
- `muted` --- uses `--mk-color-fg-muted` (secondary text color)
- `danger` --- uses `--mk-color-danger` (destructive action color)

### `underline`

- `always` --- underline is always visible
- `hover` --- underline appears on `:hover` and `:focus-visible`
- `never` --- underline is never shown

## TypeScript API

| Property | Type | Reflects |
| --- | --- | --- |
| `variant` | `'default' \| 'muted' \| 'danger' \| undefined` | Yes |
| `underline` | `'always' \| 'hover' \| 'never' \| undefined` | Yes |

Both properties reflect to and from their matching DOM attributes. Removing an attribute sets the property to `undefined`.

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) --- design tokens, page layouts, and the full component set
- [markommerce/frontend](/docs/packages/frontend/) --- component registry and `MkElement` base class
