# Task 010: `mk-divider` — Styled Separator

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Implement `mk-divider`: a thin separator line, either horizontal (default) or vertical. CSS-only behavior. Renders as a thin colored bar using `--mk-color-border` by default. Accessibility: the element should expose `role="separator"` automatically. This is the one Phase 2 primitive that touches the DOM in JS — but only to set an attribute (ARIA role) on the host itself, never to mutate children.

## Context

- Files to edit:
  - `packages/theme-blank/resources/js/components/mk-divider.ts`
  - `packages/theme-blank/resources/css/components/mk-divider.css`
- Files to create:
  - `packages/theme-blank/resources/js/components/mk-divider.test.ts`
  - `docs/src/content/docs/packages/theme-blank/mk-divider.md`

### Component API

Attributes (all optional):
- `orientation` — `horizontal | vertical`. Default: `horizontal`.

### CSS shape

```css
@layer components {
  mk-divider {
    display: block;
    background-color: var(--mk-color-border);
    border: 0;
    /* horizontal default */
    block-size: 1px;
    inline-size: 100%;
    margin-block: var(--mk-space-3);
  }

  mk-divider[orientation="vertical"] {
    block-size: auto;
    inline-size: 1px;
    align-self: stretch;
    margin-block: 0;
    margin-inline: var(--mk-space-3);
  }
}
```

### TS shape — ARIA role injection

```typescript
import { property } from 'lit/decorators.js';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-divider.css';

export class MkDividerElement extends MkElement {
  @property({ type: String, reflect: true }) orientation?: 'horizontal' | 'vertical';

  override connectedCallback(): void {
    super.connectedCallback();
    if (!this.hasAttribute('role')) {
      this.setAttribute('role', 'separator');
    }
    if (!this.hasAttribute('aria-orientation') && this.orientation === 'vertical') {
      this.setAttribute('aria-orientation', 'vertical');
    }
  }
}

registerBase('mk-divider', MkDividerElement);
```

This is **CLS-safe** because (a) the element is empty (no light-DOM children to displace), and (b) the attribute additions happen pre-paint via `connectedCallback`. The `:not(:defined)` safety-net rule from task 002 ensures the unupgraded element is still styled as a block element of the right dimensions.

### Docs page outline

Standard layout. Note the ARIA-role auto-injection and the accessibility implications.

### Test pattern

Use the shared test pattern documented in `003-mk-stack.md`. Substitute `mk-divider` / `MkDividerElement`. Note: `mk-divider` is the one Phase 2 primitive that has no expected light-DOM children, so the "preserves children" assertion does not apply — replace it with the role-injection assertions listed in the Requirements section.

## Requirements (Test Descriptions)
- [ ] `mk-divider registers under the tag name "mk-divider"`
- [ ] `MkDividerElement extends MkElement`
- [ ] `the orientation attribute reflects between the property and the DOM attribute`
- [ ] `the element gains role="separator" automatically when connected`
- [ ] `the element gains aria-orientation="vertical" when orientation="vertical" and connected`
- [ ] `the element does not override an existing role attribute set by the consumer`
- [ ] `the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-divider.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- Stylelint passes
- Docs page follows DOCS-STANDARDS
- `npm test` passes
- The default rendering produces a 1px horizontal line in `--mk-color-border`

## Implementation Notes
(Left blank — filled in by programmer during implementation)
