# Task 007: `mk-select` — Variant/Size Select Wrapper

**Status**: completed
**Depends on**: 002, 003
**Retry count**: 0

## Description
Create `mk-select` — a custom element wrapper around a native `<select>`. Same attribute model as `mk-input`. CSS adds a custom dropdown arrow via `background-image` (SVG data URI) without replacing the native select. No custom listbox — native dropdown only.

## Context
- Related files (stubs scaffolded by task 003, expand them here):
  - `packages/theme-blank/resources/js/components/mk-select.ts` (stub from 003 — extend)
  - `packages/theme-blank/resources/css/components/mk-select.css` (stub from 003 — fill in)
  - New: `packages/theme-blank/resources/js/components/mk-select.test.ts`
- Patterns to follow: task 005 (`mk-input`).
- `requireInnerControl` from `@markommerce/frontend` — call in `connectedCallback` with selector `'select'`.
- The custom arrow SVG should use `currentColor` or a fixed neutral color referencing `--mk-color-fg-muted`.

## TypeScript Shape

```typescript
import { property } from 'lit/decorators.js';
import { MkElement, registerBase, requireInnerControl } from '@markommerce/frontend';
import '../../css/components/mk-select.css';

export class MkSelectElement extends MkElement {
  @property({ type: String, reflect: true }) variant?: 'outline' | 'filled';
  @property({ type: String, reflect: true }) size?: 'sm' | 'base' | 'lg';

  override connectedCallback(): void {
    super.connectedCallback();
    requireInnerControl(this, 'select');
  }
}

registerBase('mk-select', MkSelectElement);
```

## CSS Shape (`mk-select.css`)

```css
@layer components {
  mk-select {
    display: block;
    position: relative;
  }

  mk-select > select {
    display: block;
    width: 100%;
    height: var(--mk-input-height-base);
    padding-inline: var(--mk-input-padding-inline);
    padding-inline-end: var(--mk-space-8); /* room for the arrow */
    background-color: var(--mk-input-bg);
    color: var(--mk-input-color);
    border: 1px solid var(--mk-input-border-color);
    border-radius: var(--mk-radius-input);
    font-size: var(--mk-font-size-base);
    appearance: none;
    /* custom arrow — inline SVG data URI */
    background-image: url("data:image/svg+xml,…");
    background-repeat: no-repeat;
    background-position: right var(--mk-space-3) center;
    background-size: 1em;
    cursor: pointer;
    transition: border-color var(--mk-transition-fast);
  }

  /* sizes, variant, states — mirror mk-input patterns */
}
```

## Requirements (Test Descriptions)

- [ ] `it registers under the tag name "mk-select"`
- [ ] `it MkSelectElement extends MkElement`
- [ ] `it the variant attribute reflects between property and the DOM attribute`
- [ ] `it the size attribute reflects between property and the DOM attribute`
- [ ] `it does not remove its light-DOM select child after upgrade`
- [ ] `it the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-select.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- CSS uses only `--mk-*` tokens
- All CSS inside `@layer components`
- `appearance: none` applied to suppress OS-default select styling
- Custom arrow implemented via `background-image` (not a pseudo-element overlay)
- Stylelint passes
- TypeScript compiles without errors
