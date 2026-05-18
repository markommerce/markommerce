# Task 010: `mk-switch` — Toggle-Switch Checkbox Wrapper

**Status**: completed
**Depends on**: 002, 003
**Retry count**: 0

## Description
Create `mk-switch` — a custom element wrapper around a native `<input type="checkbox">` styled visually as a pill toggle switch. The key difference from `mk-checkbox`: `connectedCallback` sets `role="switch"` on the inner input (if not already present), matching ARIA semantics for a switch control. Accepts a `size` attribute. The JS class registers the tag and warns when the inner input is missing.

## Context
- Related files (stubs scaffolded by task 003, expand them here):
  - `packages/theme-blank/resources/js/components/mk-switch.ts` (stub from 003 — extend)
  - `packages/theme-blank/resources/css/components/mk-switch.css` (stub from 003 — fill in)
  - New: `packages/theme-blank/resources/js/components/mk-switch.test.ts`
- Patterns to follow: `mk-heading.ts` (`connectedCallback` ARIA injection with guard), task 008 (`mk-checkbox`) for class shape.
- `requireInnerControl` from `@markommerce/frontend` — call in `connectedCallback` with selector `'input[type="checkbox"]'`. Consumers MUST specify `type="checkbox"` explicitly.
- ARIA guard: `if (!input.hasAttribute('role')) input.setAttribute('role', 'switch');`
- CSS creates a custom toggle-pill via pseudo-elements on the native input (or a sibling span approach — implementer's choice, must work without JS).

## TypeScript Shape

```typescript
import { property } from 'lit/decorators.js';
import { MkElement, registerBase, requireInnerControl } from '@markommerce/frontend';
import '../../css/components/mk-switch.css';

export class MkSwitchElement extends MkElement {
  @property({ type: String, reflect: true }) size?: 'sm' | 'base' | 'lg';

  override connectedCallback(): void {
    super.connectedCallback();
    const input = requireInnerControl(this, 'input[type="checkbox"]');
    if (input instanceof HTMLInputElement && !input.hasAttribute('role')) {
      input.setAttribute('role', 'switch');
    }
  }
}

registerBase('mk-switch', MkSwitchElement);
```

## CSS Shape (`mk-switch.css`)

Toggle-pill via `appearance: none` + pseudo-elements on the native checkbox:

```css
@layer components {
  mk-switch {
    display: inline-flex;
    align-items: center;
    gap: var(--mk-space-2);
  }

  mk-switch > input[type="checkbox"] {
    appearance: none;
    width: 2.5rem;
    height: 1.5rem;
    border-radius: var(--mk-radius-full);
    background-color: var(--mk-color-border);
    position: relative;
    cursor: pointer;
    flex-shrink: 0;
    transition: background-color var(--mk-transition-fast);
  }

  mk-switch > input[type="checkbox"]::before {
    content: '';
    position: absolute;
    inset-block: 0.125rem;
    inset-inline-start: 0.125rem;
    width: 1.25rem;
    border-radius: var(--mk-radius-full);
    background-color: white;
    transition: translate var(--mk-transition-fast);
  }

  mk-switch > input[type="checkbox"]:checked {
    background-color: var(--mk-color-primary);
  }

  mk-switch > input[type="checkbox"]:checked::before {
    translate: 1rem 0;
  }

  mk-switch:has(input:focus-visible) > input[type="checkbox"] {
    outline: 2px solid var(--mk-color-focus-ring);
    outline-offset: 2px;
  }

  mk-switch:has(input:disabled) { opacity: 0.5; cursor: not-allowed; }

  /* sizes */
  mk-switch[size="sm"] > input[type="checkbox"] { width: 2rem; height: 1.25rem; }
  mk-switch[size="lg"] > input[type="checkbox"] { width: 3rem; height: 1.75rem; }
}
```

## Requirements (Test Descriptions)

- [ ] `it registers under the tag name "mk-switch"`
- [ ] `it MkSwitchElement extends MkElement`
- [ ] `it the size attribute reflects between property and the DOM attribute`
- [ ] `it sets role="switch" on the inner input in connectedCallback`
- [ ] `it does not override an existing role attribute set by the server`
- [ ] `it does not remove its light-DOM input child after upgrade`
- [ ] `it the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-switch.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- CSS uses only `--mk-*` tokens
- All CSS inside `@layer components`
- Toggle animation via CSS only (`transition` on `translate`)
- `role="switch"` guard matches the `mk-heading` ARIA guard pattern
- Stylelint passes
- TypeScript compiles without errors
