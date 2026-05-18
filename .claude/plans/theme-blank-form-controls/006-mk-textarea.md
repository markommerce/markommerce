# Task 006: `mk-textarea` — Variant/Size Textarea Wrapper

**Status**: completed
**Depends on**: 002, 003
**Retry count**: 0

## Description
Create `mk-textarea` — a custom element wrapper around a native `<textarea>`. Mirrors `mk-input` in attribute model and CSS approach but without a fixed height (textareas grow with content). The JS class registers the tag and warns when the inner textarea is missing.

## Context
- Related files (stubs scaffolded by task 003, expand them here):
  - `packages/theme-blank/resources/js/components/mk-textarea.ts` (stub from 003 — extend)
  - `packages/theme-blank/resources/css/components/mk-textarea.css` (stub from 003 — fill in)
  - New: `packages/theme-blank/resources/js/components/mk-textarea.test.ts`
- Patterns to follow: task 005 (`mk-input`) — same attribute model, same `:has()` pattern.
- `requireInnerControl` from `@markommerce/frontend` — call in `connectedCallback` with selector `'textarea'`.

## TypeScript Shape

```typescript
import { property } from 'lit/decorators.js';
import { MkElement, registerBase, requireInnerControl } from '@markommerce/frontend';
import '../../css/components/mk-textarea.css';

export class MkTextareaElement extends MkElement {
  @property({ type: String, reflect: true }) variant?: 'outline' | 'filled';
  @property({ type: String, reflect: true }) size?: 'sm' | 'base' | 'lg';

  override connectedCallback(): void {
    super.connectedCallback();
    requireInnerControl(this, 'textarea');
  }
}

registerBase('mk-textarea', MkTextareaElement);
```

## CSS Shape (`mk-textarea.css`)

```css
@layer components {
  mk-textarea {
    display: block;
  }

  mk-textarea > textarea {
    display: block;
    width: 100%;
    min-height: var(--mk-input-height-base);
    padding: var(--mk-space-2) var(--mk-input-padding-inline);
    background-color: var(--mk-input-bg);
    color: var(--mk-input-color);
    border: 1px solid var(--mk-input-border-color);
    border-radius: var(--mk-radius-input);
    font-size: var(--mk-font-size-base);
    font-family: var(--mk-font-sans);
    resize: vertical;
    transition: border-color var(--mk-transition-fast);
  }

  mk-textarea > textarea::placeholder { color: var(--mk-input-color-placeholder); }

  mk-textarea[size="sm"] > textarea { min-height: var(--mk-input-height-sm); font-size: var(--mk-font-size-sm); }
  mk-textarea[size="lg"] > textarea { min-height: var(--mk-input-height-lg); font-size: var(--mk-font-size-lg); }
  mk-textarea[variant="filled"] > textarea { background-color: var(--mk-color-border); border-color: transparent; }

  mk-textarea:has(textarea:focus-visible) > textarea { border-color: var(--mk-input-border-color-focus); outline: 2px solid var(--mk-color-focus-ring); outline-offset: 2px; }
  mk-textarea:has(textarea:disabled) { opacity: 0.5; }
  mk-textarea:has(textarea:user-invalid) > textarea { border-color: var(--mk-input-border-color-error); }
  mk-textarea:has(textarea:user-valid) > textarea { border-color: var(--mk-color-success); }
}
```

## Requirements (Test Descriptions)

- [ ] `it registers under the tag name "mk-textarea"`
- [ ] `it MkTextareaElement extends MkElement`
- [ ] `it the variant attribute reflects between property and the DOM attribute`
- [ ] `it the size attribute reflects between property and the DOM attribute`
- [ ] `it does not remove its light-DOM textarea child after upgrade`
- [ ] `it the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-textarea.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- CSS uses only `--mk-*` tokens
- All CSS inside `@layer components`
- Stylelint passes
- TypeScript compiles without errors
