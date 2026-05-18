# Task 009: `mk-cover` — Header/Main/Footer Full-Height Frame

**Status**: complete
**Depends on**: 002
**Retry count**: 0

## Description
Implement `mk-cover`: a vertical layout with optional header at top, optional footer at bottom, and a centered main region that fills the available space. Used for full-viewport landing sections, hero areas, and modal content. Uses CSS Grid with a `1fr` track that absorbs remaining space. **CSS-only behavior.**

## Context

- Files to edit:
  - `packages/theme-blank/resources/js/components/mk-cover.ts`
  - `packages/theme-blank/resources/css/components/mk-cover.css`
- Files to create:
  - `packages/theme-blank/resources/js/components/mk-cover.test.ts`
  - `docs/src/content/docs/packages/theme-blank/mk-cover.md`

### Component API

Attributes (all optional):
- `min-height` — CSS length. Minimum height of the cover. Default: `100vh`. Synced to `--mk-cover-min-height` inline style.

### Semantic structure

The cover uses **named slot conventions via classes**, not Shadow-DOM slots (since we're light-DOM-only):
- A direct child with `class="mk-cover-header"` becomes the header.
- A direct child with `class="mk-cover-footer"` becomes the footer.
- All other direct children become the centered main region.

### CSS shape

```css
@layer components {
  mk-cover {
    display: grid;
    min-height: var(--mk-cover-min-height, 100vh);
    grid-template-rows: auto 1fr auto;
    gap: var(--mk-space-4);
    padding: var(--mk-space-4);
  }

  /* The middle 1fr track centers its content vertically when it overflows the main region's min-content */
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

### TS shape

**CLS hazard — same as `mk-grid` / `mk-sidebar` / `mk-switcher`.** Sync in `connectedCallback` synchronously, reading directly from the `min-height` HTML attribute.

```typescript
import { property } from 'lit/decorators.js';
import type { PropertyValues } from 'lit';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-cover.css';

export class MkCoverElement extends MkElement {
  @property({ type: String, reflect: true, attribute: 'min-height' }) minHeight?: string;

  override connectedCallback(): void {
    const attrValue = this.getAttribute('min-height');
    if (attrValue !== null && attrValue !== '') {
      this.style.setProperty('--mk-cover-min-height', attrValue);
    }
    super.connectedCallback();
  }

  override updated(changed: PropertyValues<this>): void {
    super.updated(changed);
    if (changed.has('minHeight')) {
      if (this.minHeight) {
        this.style.setProperty('--mk-cover-min-height', this.minHeight);
      } else {
        this.style.removeProperty('--mk-cover-min-height');
      }
    }
  }
}

registerBase('mk-cover', MkCoverElement);
```

### Docs page outline

Standard layout. Document the class-based slot convention explicitly with an HTML example. Also document the CLS guidance: for non-default `min-height` values, emit `style="--mk-cover-min-height: <value>"` on the server-rendered tag if first-paint correctness matters.

### Test pattern

Use the shared test pattern documented in `003-mk-stack.md`. Substitute `mk-cover` / `MkCoverElement`.

```html
<mk-cover min-height="80vh">
  <header class="mk-cover-header">Top</header>
  <section>Centered main content</section>
  <footer class="mk-cover-footer">Bottom</footer>
</mk-cover>
```

## Requirements (Test Descriptions)
- [x] `mk-cover registers under the tag name "mk-cover"`
- [x] `MkCoverElement extends MkElement`
- [x] `the min-height attribute reflects between the minHeight property and the DOM attribute`
- [x] `setting the minHeight property writes --mk-cover-min-height as an inline style on the element`
- [x] `clearing the minHeight property removes the --mk-cover-min-height inline style`
- [x] `parsing <mk-cover min-height="80vh"></mk-cover> and upgrading sets style="--mk-cover-min-height: 80vh" synchronously during connectedCallback — assert immediately after defineAllComponents() returns, without awaiting updateComplete`
- [x] `appending mk-cover to the DOM does not remove its light-DOM children`
- [x] `the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-cover.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- Stylelint passes
- Docs page follows DOCS-STANDARDS
- `npm test` passes
- Default attribute-less rendering produces a 100vh min-height grid with the centered main region

## Implementation Notes
- Used `ComplexAttributeConverter<string | undefined>` to map absent attributes to `undefined` (same pattern as `mk-sidebar.ts`).
- Import ordering: `lit/decorators.js` and `import type { ... } from 'lit'` come before `@markommerce/frontend` to avoid module resolution issues in the happy-dom test environment.
- CSS `:not()` selector uses the complex form `mk-cover > :not(.mk-cover-header, .mk-cover-footer)` (single `:not()` with comma-separated args) to satisfy the `selector-not-notation` stylelint rule.
- All 8 requirements pass in 335/335 total test run.
