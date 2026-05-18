# Task 005: `mk-input` — Variant/Size Text Input Wrapper

**Status**: completed
**Depends on**: 002, 003
**Retry count**: 0

## Description
Create `mk-input` — a custom element wrapper around a native `<input>`. The wrapper adds visual variants (`outline`, `filled`) and sizes (`sm`, `base`, `lg`). All state (focus, disabled, invalid, valid) is surfaced via CSS `:has()` selectors targeting the inner input — no JS state mirroring. The JS class registers the tag and warns when the inner input is missing.

## Context
- Related files (stubs scaffolded by task 003, expand them here):
  - `packages/theme-blank/resources/js/components/mk-input.ts` (stub from 003 — extend)
  - `packages/theme-blank/resources/css/components/mk-input.css` (stub from 003 — fill in)
  - New: `packages/theme-blank/resources/js/components/mk-input.test.ts`
- Patterns to follow: `mk-link.ts` (wrapper pattern, `optionalString` converter), `mk-badge.css` (variant attribute selectors).
- `requireInnerControl` from `@markommerce/frontend` — call in `connectedCallback` with selector `'input'`.

## TypeScript Shape

```typescript
import { property } from 'lit/decorators.js';
import { MkElement, registerBase, requireInnerControl } from '@markommerce/frontend';
import '../../css/components/mk-input.css';

export class MkInputElement extends MkElement {
  @property({ type: String, reflect: true }) variant?: 'outline' | 'filled';
  @property({ type: String, reflect: true }) size?: 'sm' | 'base' | 'lg';

  override connectedCallback(): void {
    super.connectedCallback();
    requireInnerControl(this, 'input');
  }
}

registerBase('mk-input', MkInputElement);
```

## CSS Shape (`mk-input.css`)

```css
@layer components {
  mk-input {
    display: block;
  }

  mk-input > input {
    display: block;
    width: 100%;
    height: var(--mk-input-height-base);
    padding-inline: var(--mk-input-padding-inline);
    background-color: var(--mk-input-bg);
    color: var(--mk-input-color);
    border: 1px solid var(--mk-input-border-color);
    border-radius: var(--mk-radius-input);
    font-size: var(--mk-font-size-base);
    transition: border-color var(--mk-transition-fast);
  }

  mk-input > input::placeholder {
    color: var(--mk-input-color-placeholder);
  }

  /* size variants */
  mk-input[size="sm"] > input { height: var(--mk-input-height-sm); font-size: var(--mk-font-size-sm); }
  mk-input[size="lg"] > input { height: var(--mk-input-height-lg); font-size: var(--mk-font-size-lg); }

  /* filled variant */
  mk-input[variant="filled"] > input { background-color: var(--mk-color-border); border-color: transparent; }

  /* state via :has() */
  mk-input:has(input:focus-visible) > input { border-color: var(--mk-input-border-color-focus); outline: 2px solid var(--mk-color-focus-ring); outline-offset: 2px; }
  mk-input:has(input:disabled) { opacity: 0.5; }
  mk-input:has(input:user-invalid) > input { border-color: var(--mk-input-border-color-error); }
  mk-input:has(input:user-valid) > input { border-color: var(--mk-color-success); }
}
```

## Requirements (Test Descriptions)

- [ ] `it registers under the tag name "mk-input"`
- [ ] `it MkInputElement extends MkElement`
- [ ] `it the variant attribute reflects between property and the DOM attribute`
- [ ] `it the size attribute reflects between property and the DOM attribute`
- [ ] `it does not remove its light-DOM input child after upgrade`
- [ ] `it the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-input.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- CSS uses only `--mk-*` tokens
- All CSS inside `@layer components`
- Stylelint passes
- TypeScript compiles without errors
