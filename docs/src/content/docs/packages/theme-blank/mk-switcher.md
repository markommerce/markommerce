---
title: mk-switcher
description: Flex-based layout primitive that switches between horizontal and vertical stacking when the container narrows past a threshold --- no media queries, no JS measurement.
---

`mk-switcher` implements the Every Layout "switcher" pattern. Children are laid out in a horizontal row as long as each child has enough room. When the container becomes too narrow, all children switch to a vertical stack simultaneously. The breakpoint is determined by pure CSS flex-basis arithmetic --- no `@container` query and no JavaScript measurement needed.

## Usage

```html
<mk-switcher>
  <div>Item one</div>
  <div>Item two</div>
  <div>Item three</div>
</mk-switcher>
```

Override the switch point and gap:

```html
<mk-switcher threshold="40rem" gap="4">
  <div>Item one</div>
  <div>Item two</div>
</mk-switcher>
```

## Attributes

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `threshold` | CSS length string | `30rem` | Container width below which children stack vertically. Synced to `--mk-switcher-threshold` as an inline style. |
| `gap` | `0`--`9` | `3` | Gap between children, mapped to `--mk-space-{n}`. |
| `limit` | integer | `4` | Maximum number of children displayed horizontally. Reflected as an attribute but consumed by CSS only (baked in at 4). |

## CSS

`mk-switcher` uses flex-wrap and a calculated `flex-basis` to achieve the simultaneous switch behavior. The threshold is exposed as the `--mk-switcher-threshold` custom property:

```css
@layer components {
  mk-switcher {
    display: flex;
    flex-wrap: wrap;
    gap: var(--mk-space-3);
  }

  mk-switcher > * {
    flex-grow: 1;
    flex-basis: calc((var(--mk-switcher-threshold, 30rem) - 100%) * 999);
  }

  /* When more than 4 children exist, force each to 100% width */
  mk-switcher > :nth-last-child(n + 5),
  mk-switcher > :nth-last-child(n + 5) ~ * {
    flex-basis: 100%;
  }
}
```

The `(threshold - 100%) * 999` expression collapses each child's `flex-basis` to a large negative number when the container width exceeds the threshold (making all children fit in one row) and grows it to a large positive number when the container is narrower than the threshold (forcing each child to wrap to its own row). Because all children calculate their basis from the same container width, the switch is simultaneous.

## JavaScript API

The `MkSwitcherElement` class manages the `--mk-switcher-threshold` CSS custom property as an inline style, ensuring a layout-shift-free upgrade path.

### CLS Prevention

The `threshold` value is applied synchronously in `connectedCallback` --- before `super.connectedCallback()` --- so the inline style is present on the element immediately when the page parses it, avoiding any cumulative layout shift caused by a Lit render cycle.

```typescript
const el = document.querySelector('mk-switcher') as HTMLElement & {
  threshold?: string;
  gap?: string;
  limit?: number;
};

// Setting threshold updates --mk-switcher-threshold immediately
el.threshold = '40rem';

// Clearing threshold removes the custom property
el.threshold = undefined;
```

### Properties

| Property | Type | Description |
| --- | --- | --- |
| `threshold` | `string \| undefined` | CSS length value. When set, written to `--mk-switcher-threshold` as an inline style. When unset, the custom property is removed (the CSS fallback `30rem` applies). |
| `gap` | `string \| undefined` | Gap index `0`--`9`. Reflected as a DOM attribute; consumed by CSS gap attribute selectors. |
| `limit` | `number \| undefined` | Horizontal child limit. Reflected as a DOM attribute; the CSS limit rule is baked in at 4. |

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) --- the parent package that ships all layout primitives and design tokens.
- [markommerce/frontend](/docs/packages/frontend/) --- provides `MkElement`, `registerBase`, and the component registry.
