# Task 007: `mk-sidebar` — Sidebar + Main with `@container` Collapse

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Implement `mk-sidebar`: a two-child layout primitive with a fixed-width sidebar adjacent to a flex-growing main content area. Collapses to a single vertical stack when the available inline size drops below a threshold. Uses CSS `@container` queries — *not* viewport media queries — so the collapse is driven by the element's actual available space, not the window width. **CSS-only behavior.** First child is always the sidebar; second child is the main area.

## Context

- Files to edit:
  - `packages/theme-blank/resources/js/components/mk-sidebar.ts`
  - `packages/theme-blank/resources/css/components/mk-sidebar.css`
- Files to create:
  - `packages/theme-blank/resources/js/components/mk-sidebar.test.ts`
  - `docs/src/content/docs/packages/theme-blank/mk-sidebar.md`

### Component API

Attributes (all optional):
- `side` — `left | right`. Default: `left`. Determines which child is the sidebar.
- `width` — CSS length for the sidebar. Default: `15rem`. Synced to `--mk-sidebar-width` inline style.
- `threshold` — minimum inline-size of the container at which side-by-side layout is allowed. Below the threshold, content stacks. Default: `40rem`. Synced to `--mk-sidebar-threshold` inline style (consumed by the `@container` query via a `style()` query feature — see Implementation Notes).

### CSS shape

`@container` style queries (`@container style(--mk-sidebar-threshold: 40rem)`) are NOT supported in all target browsers yet for arbitrary properties. Use a simpler approach: define the threshold as a CSS variable on the element and use a fixed-set of container queries:

```css
@layer components {
  mk-sidebar {
    container-type: inline-size;
    container-name: mk-sidebar;
    display: flex;
    flex-wrap: wrap;
    gap: var(--mk-space-4);
  }

  mk-sidebar > :first-child,
  mk-sidebar > :last-child {
    flex-basis: var(--mk-sidebar-width, 15rem);
    flex-grow: 1;
  }

  mk-sidebar > :last-child {
    flex-basis: 0;
    flex-grow: 999;
    min-inline-size: 50%;
  }

  mk-sidebar[side="right"] > :first-child {
    flex-basis: 0;
    flex-grow: 999;
    min-inline-size: 50%;
  }

  mk-sidebar[side="right"] > :last-child {
    flex-basis: var(--mk-sidebar-width, 15rem);
    flex-grow: 1;
    min-inline-size: auto;
  }
}
```

This is the **Every Layout "sidebar"** pattern: pure flexbox, no container query needed. The collapse threshold is implicit in `min-inline-size: 50%` — when the main pane can't fit 50% of the parent width while keeping the sidebar at its `flex-basis`, the main pane wraps under. This is more robust than `@container` queries because it works in older browsers and doesn't require explicit threshold values.

If a downstream consumer wants a custom collapse threshold, they override `min-inline-size` on the appropriate child in their own CSS layer. The component's `threshold` attribute is therefore **dropped** in favor of the implicit-flexbox approach.

**Decision:** simplify the API to just `side` and `width`. Document the implicit-collapse behavior on the docs page.

### Revised API

- `side` — `left | right`. Default: `left`.
- `width` — CSS length. Default: `15rem`. Synced to `--mk-sidebar-width` inline style.

### TS shape

**CLS hazard — same as `mk-grid`.** The `width` attribute synced from JS would cause a post-paint reflow if synced in `updated()` (a microtask) with a deferred bundle. Sync in `connectedCallback` synchronously instead, reading directly from the attribute.

```typescript
import { property } from 'lit/decorators.js';
import type { PropertyValues } from 'lit';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-sidebar.css';

export class MkSidebarElement extends MkElement {
  @property({ type: String, reflect: true }) side?: 'left' | 'right';
  @property({ type: String, reflect: true }) width?: string;

  override connectedCallback(): void {
    const attrValue = this.getAttribute('width');
    if (attrValue !== null && attrValue !== '') {
      this.style.setProperty('--mk-sidebar-width', attrValue);
    }
    super.connectedCallback();
  }

  override updated(changed: PropertyValues<this>): void {
    super.updated(changed);
    if (changed.has('width')) {
      if (this.width) {
        this.style.setProperty('--mk-sidebar-width', this.width);
      } else {
        this.style.removeProperty('--mk-sidebar-width');
      }
    }
  }
}

registerBase('mk-sidebar', MkSidebarElement);
```

### Docs page outline

Standard layout. Explain the *implicit* collapse behavior and how to customize it via the `--mk-sidebar-width` custom property or by overriding `min-inline-size` in a downstream layer. Also document the CLS guidance: for non-default values, emit `style="--mk-sidebar-width: <value>"` on the server-rendered tag if first-paint correctness matters.

### Test pattern

Use the shared test pattern documented in `003-mk-stack.md`. Substitute `mk-sidebar` / `MkSidebarElement`.

## Requirements (Test Descriptions)
- [ ] `mk-sidebar registers under the tag name "mk-sidebar"`
- [ ] `MkSidebarElement extends MkElement`
- [ ] `the side attribute reflects between the property and the DOM attribute`
- [ ] `the width attribute reflects between the property and the DOM attribute`
- [ ] `setting the width property writes --mk-sidebar-width as an inline style on the element`
- [ ] `clearing the width property removes the --mk-sidebar-width inline style`
- [ ] `parsing <mk-sidebar width="14rem"></mk-sidebar> and upgrading sets style="--mk-sidebar-width: 14rem" synchronously during connectedCallback — assert immediately after defineAllComponents() returns, without awaiting updateComplete`
- [ ] `appending mk-sidebar to the DOM does not remove its light-DOM children`
- [ ] `the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-sidebar.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- Stylelint passes
- Docs page follows DOCS-STANDARDS
- `npm test` passes
- The collapse behavior verified visually in the demo route (task 016)

## Implementation Notes
(Left blank — filled in by programmer during implementation)
