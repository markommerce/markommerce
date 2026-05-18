# Task 008: `mk-checkbox` — Styled Checkbox Wrapper

**Status**: completed
**Depends on**: 002, 003
**Retry count**: 0

## Description
Create `mk-checkbox` — a custom element wrapper around a native `<input type="checkbox">`. Applies consistent visual styling via `accent-color` and supplementary CSS. Accepts a `size` attribute. The wrapper may contain an inline `<label>` for click-to-toggle convenience. The JS class registers the tag and warns when the inner checkbox is missing.

## Context
- Related files (stubs scaffolded by task 003, expand them here):
  - `packages/theme-blank/resources/js/components/mk-checkbox.ts` (stub from 003 — extend)
  - `packages/theme-blank/resources/css/components/mk-checkbox.css` (stub from 003 — fill in)
  - New: `packages/theme-blank/resources/js/components/mk-checkbox.test.ts`
- Patterns to follow: task 005 (`mk-input`) for the class shape; `mk-badge.css` for single-property variant selectors.
- `requireInnerControl` from `@markommerce/frontend` — call in `connectedCallback` with selector `'input[type="checkbox"]'`. Consumers MUST specify `type="checkbox"` explicitly; the warning will fire if they do not.
- Use CSS `accent-color: var(--mk-color-primary)` for cross-browser checkbox tinting. No custom pseudo-element checkbox to avoid a11y complications.

## TypeScript Shape

```typescript
import { property } from 'lit/decorators.js';
import { MkElement, registerBase, requireInnerControl } from '@markommerce/frontend';
import '../../css/components/mk-checkbox.css';

export class MkCheckboxElement extends MkElement {
  @property({ type: String, reflect: true }) size?: 'sm' | 'base' | 'lg';

  override connectedCallback(): void {
    super.connectedCallback();
    requireInnerControl(this, 'input[type="checkbox"]');
  }
}

registerBase('mk-checkbox', MkCheckboxElement);
```

## CSS Shape (`mk-checkbox.css`)

```css
@layer components {
  mk-checkbox {
    display: inline-flex;
    align-items: center;
    gap: var(--mk-space-2);
  }

  mk-checkbox > input[type="checkbox"] {
    accent-color: var(--mk-color-primary);
    width: 1rem;
    height: 1rem;
    cursor: pointer;
    flex-shrink: 0;
    transition: box-shadow var(--mk-transition-fast);
  }

  mk-checkbox[size="sm"] > input[type="checkbox"] { width: 0.875rem; height: 0.875rem; }
  mk-checkbox[size="lg"] > input[type="checkbox"] { width: 1.25rem; height: 1.25rem; }

  mk-checkbox:has(input:focus-visible) > input[type="checkbox"] {
    outline: 2px solid var(--mk-color-focus-ring);
    outline-offset: 2px;
  }

  mk-checkbox:has(input:disabled) { opacity: 0.5; cursor: not-allowed; }
}
```

## Requirements (Test Descriptions)

- [ ] `it registers under the tag name "mk-checkbox"`
- [ ] `it MkCheckboxElement extends MkElement`
- [ ] `it the size attribute reflects between property and the DOM attribute`
- [ ] `it does not remove its light-DOM input child after upgrade`
- [ ] `it the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-checkbox.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- CSS uses only `--mk-*` tokens
- All CSS inside `@layer components`
- Uses `accent-color` for tinting (no custom pseudo-element checkbox)
- Stylelint passes
- TypeScript compiles without errors
