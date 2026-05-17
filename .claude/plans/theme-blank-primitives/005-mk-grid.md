# Task 005: `mk-grid` — Auto-Fit Responsive Grid

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Implement `mk-grid`: a responsive auto-fit CSS Grid container. Children laid out in equal columns that shrink/wrap automatically based on a minimum column width. CSS-only behavior using `grid-template-columns: repeat(auto-fit, minmax(min(<min>, 100%), 1fr))` to avoid overflow on narrow viewports. Defaults: `display: grid; gap: var(--mk-space-3); grid-template-columns: repeat(auto-fit, minmax(min(16rem, 100%), 1fr));`.

## Context

- Files to edit:
  - `packages/theme-blank/resources/js/components/mk-grid.ts`
  - `packages/theme-blank/resources/css/components/mk-grid.css`
- Files to create:
  - `packages/theme-blank/resources/js/components/mk-grid.test.ts`
  - `docs/src/content/docs/packages/theme-blank/mk-grid.md`

### Component API

Attributes (all optional):
- `min` — minimum column width before wrapping. Accepts CSS length (e.g., `"16rem"`, `"200px"`). Default: `16rem`. Implemented via a CSS custom property `--mk-grid-min` set inline on the element when the attribute is present.
- `gap` — `0..9` mapped to `--mk-space-{n}`. Default: `3`.

### CSS shape

```css
@layer components {
  mk-grid {
    display: grid;
    gap: var(--mk-space-3);
    grid-template-columns: repeat(auto-fit, minmax(min(var(--mk-grid-min, 16rem), 100%), 1fr));
  }
  mk-grid[gap="0"] { gap: var(--mk-space-0); }
  /* … through 9 … */
}
```

### TS — `min` attribute strategy

The `min` attribute is reflected to the DOM and also synced to a CSS custom property on the element so the CSS can pick it up.

**CLS hazard — must sync synchronously in `connectedCallback`, not `updated()`.** If the sync runs in `updated()` (a microtask), the JS bundle loaded with `defer` will upgrade the element *after* first paint with `var(--mk-grid-min, 16rem)` resolving to 16rem; then `updated()` will set `--mk-grid-min: 20rem` (or whatever the consumer requested), causing a layout shift. Doing the sync inside `connectedCallback()` *before* `super.connectedCallback()` and reading directly from the attribute (not the property, which Lit hasn't initialised yet at that point) sets the inline style synchronously during upgrade, which the browser groups into the same layout pass as the upgrade itself — and lets the consumer also "preload" the inline style server-side via `style="--mk-grid-min: 20rem"` for the pre-upgrade phase.

```typescript
import { property } from 'lit/decorators.js';
import type { PropertyValues } from 'lit';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-grid.css';

export class MkGridElement extends MkElement {
  @property({ type: String, reflect: true }) min?: string;
  @property({ type: String, reflect: true }) gap?: string;

  override connectedCallback(): void {
    // Sync synchronously BEFORE Lit schedules its first update so the inline
    // style is set in the same layout pass as the upgrade.
    const attrValue = this.getAttribute('min');
    if (attrValue !== null && attrValue !== '') {
      this.style.setProperty('--mk-grid-min', attrValue);
    }
    super.connectedCallback();
  }

  override updated(changed: PropertyValues<this>): void {
    super.updated(changed);
    if (changed.has('min')) {
      if (this.min) {
        this.style.setProperty('--mk-grid-min', this.min);
      } else {
        this.style.removeProperty('--mk-grid-min');
      }
    }
  }
}

registerBase('mk-grid', MkGridElement);
```

This is one of the few cases where a Phase 2 component touches the DOM in JS — but only by writing a CSS custom property, never by replacing children. CLS invariant is preserved.

**Consumer guidance for the docs page:** if a non-default `min` value is critical for first paint (i.e. the consumer cannot tolerate even a microtask-deferred shift), they should also emit `style="--mk-grid-min: <value>"` on the server-rendered `<mk-grid>` tag. The component will not overwrite an existing inline value with the same value, so this is idempotent.

### Docs page outline

Standard layout. Explain the `min` attribute syntax and the `--mk-grid-min` CSS custom property as both a JS-sync target and a direct override hook. Also document the CLS guidance from the Implementation Notes: for non-default values, emit `style="--mk-grid-min: <value>"` on the server-rendered tag if first-paint correctness matters.

### Test pattern

Use the shared test pattern documented in `003-mk-stack.md`. Substitute `mk-grid` / `MkGridElement`.

## Requirements (Test Descriptions)
- [ ] `mk-grid registers under the tag name "mk-grid"`
- [ ] `MkGridElement extends MkElement`
- [ ] `the gap attribute reflects between the property and the DOM attribute`
- [ ] `the min attribute reflects between the property and the DOM attribute`
- [ ] `setting the min property writes --mk-grid-min as an inline style on the element`
- [ ] `clearing the min property removes the --mk-grid-min inline style`
- [ ] `parsing <mk-grid min="20rem"></mk-grid> via innerHTML and triggering upgrade (defineAllComponents) sets style="--mk-grid-min: 20rem" synchronously during connectedCallback — assert immediately after defineAllComponents() returns, without awaiting updateComplete`
- [ ] `appending mk-grid to the DOM does not remove its light-DOM children`
- [ ] `the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-grid.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- Stylelint passes
- Docs page follows DOCS-STANDARDS
- `npm test` passes
- Default attribute-less rendering produces an auto-fit grid with `var(--mk-space-3)` gap and 16rem minimum column width

## Implementation Notes
(Left blank — filled in by programmer during implementation)
