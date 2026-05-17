# Task 008: `mk-switcher` — Row→Column Switch via Implicit Flexbox

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Implement `mk-switcher`: children laid out horizontally as long as each child has enough room to display at its natural width; when the container becomes too narrow, all children switch to vertical stacking simultaneously. Uses the **Every Layout "switcher"** pattern based on flex-basis arithmetic — no `@container` query, no JS measurement. CSS-only behavior.

## Context

- Files to edit:
  - `packages/theme-blank/resources/js/components/mk-switcher.ts`
  - `packages/theme-blank/resources/css/components/mk-switcher.css`
- Files to create:
  - `packages/theme-blank/resources/js/components/mk-switcher.test.ts`
  - `docs/src/content/docs/packages/theme-blank/mk-switcher.md`

### Component API

Attributes (all optional):
- `threshold` — CSS length. Below this container width, children stack. Default: `30rem`. Synced to `--mk-switcher-threshold` inline style.
- `gap` — `0..9` mapped to `--mk-space-{n}`. Default: `3`.
- `limit` — integer. Maximum number of children displayed horizontally. When exceeded, the whole switcher stacks. Default: `4`. Synced to `--mk-switcher-limit` inline style.

### CSS shape

```css
@layer components {
  mk-switcher {
    display: flex;
    flex-wrap: wrap;
    gap: var(--mk-space-3);
  }
  mk-switcher[gap="0"] { gap: var(--mk-space-0); }
  /* … through 9 … */

  mk-switcher > * {
    flex-grow: 1;
    flex-basis: calc((var(--mk-switcher-threshold, 30rem) - 100%) * 999);
  }

  /* When more than --mk-switcher-limit children exist, force each to 100% width */
  mk-switcher > :nth-last-child(n + 5),
  mk-switcher > :nth-last-child(n + 5) ~ * {
    flex-basis: 100%;
  }
}
```

The arithmetic `calc((threshold - 100%) * 999)`: when the container is narrower than threshold, this calc produces a large positive number (children consume full width and wrap individually); when wider, it produces a large negative number (clamped to `flex-basis: 0`, so flex-grow distributes space equally). The `:nth-last-child(n + 5)` rule handles the `limit` of 4. (Higher limits would require a different selector or `:has()` — Phase 2 ships with limit 4 baked in; document the constraint.)

### TS shape

**CLS hazard — same as `mk-grid` / `mk-sidebar`.** The `threshold` attribute synced from JS would cause a post-paint reflow if synced in `updated()` (a microtask) with a deferred bundle. Sync in `connectedCallback` synchronously instead, reading directly from the attribute.

```typescript
import { property } from 'lit/decorators.js';
import type { PropertyValues } from 'lit';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-switcher.css';

export class MkSwitcherElement extends MkElement {
  @property({ type: String, reflect: true }) threshold?: string;
  @property({ type: String, reflect: true }) gap?: string;
  @property({ type: Number, reflect: true }) limit?: number;

  override connectedCallback(): void {
    const attrValue = this.getAttribute('threshold');
    if (attrValue !== null && attrValue !== '') {
      this.style.setProperty('--mk-switcher-threshold', attrValue);
    }
    super.connectedCallback();
  }

  override updated(changed: PropertyValues<this>): void {
    super.updated(changed);
    if (changed.has('threshold')) {
      if (this.threshold) {
        this.style.setProperty('--mk-switcher-threshold', this.threshold);
      } else {
        this.style.removeProperty('--mk-switcher-threshold');
      }
    }
  }
}

registerBase('mk-switcher', MkSwitcherElement);
```

The `limit` attribute is **reflected** but not consumed by JS — it's a documentation hint. The actual limit (4) is baked into the CSS. A future enhancement could use `:has(:nth-child(N))` to make limit dynamic, but that's out of scope for Phase 2.

### Docs page outline

Standard layout. Explain the Every Layout pattern in plain language. Note the hard-coded limit of 4 for Phase 2. Also document the CLS guidance: for non-default `threshold` values, emit `style="--mk-switcher-threshold: <value>"` on the server-rendered tag if first-paint correctness matters.

### Test pattern

Use the shared test pattern documented in `003-mk-stack.md`. Substitute `mk-switcher` / `MkSwitcherElement`.

## Requirements (Test Descriptions)
- [ ] `mk-switcher registers under the tag name "mk-switcher"`
- [ ] `MkSwitcherElement extends MkElement`
- [ ] `the threshold attribute reflects between the property and the DOM attribute`
- [ ] `the gap attribute reflects between the property and the DOM attribute`
- [ ] `the limit attribute reflects between the property and the DOM attribute`
- [ ] `setting the threshold property writes --mk-switcher-threshold as an inline style on the element`
- [ ] `clearing the threshold property removes the --mk-switcher-threshold inline style`
- [ ] `parsing <mk-switcher threshold="20rem"></mk-switcher> and upgrading sets style="--mk-switcher-threshold: 20rem" synchronously during connectedCallback — assert immediately after defineAllComponents() returns, without awaiting updateComplete`
- [ ] `appending mk-switcher to the DOM does not remove its light-DOM children`
- [ ] `the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-switcher.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- Stylelint passes
- Docs page follows DOCS-STANDARDS
- `npm test` passes
- The switch behavior verified visually in the demo route (task 016)

## Implementation Notes
(Left blank — filled in by programmer during implementation)
