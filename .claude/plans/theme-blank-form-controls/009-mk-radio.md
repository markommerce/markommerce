# Task 009: `mk-radio` — Styled Radio Wrapper

**Status**: completed
**Depends on**: 002, 003
**Retry count**: 0

## Description
Create `mk-radio` — a custom element wrapper around a native `<input type="radio">`. Mirrors `mk-checkbox` in structure and CSS approach. Accepts a `size` attribute. The JS class registers the tag and warns when the inner radio is missing.

## Context
- Related files (stubs scaffolded by task 003, expand them here):
  - `packages/theme-blank/resources/js/components/mk-radio.ts` (stub from 003 — extend)
  - `packages/theme-blank/resources/css/components/mk-radio.css` (stub from 003 — fill in)
  - New: `packages/theme-blank/resources/js/components/mk-radio.test.ts`
- Patterns to follow: task 008 (`mk-checkbox`) — identical structure, swap `checkbox` for `radio` throughout.
- `requireInnerControl` from `@markommerce/frontend` — call in `connectedCallback` with selector `'input[type="radio"]'`. Consumers MUST specify `type="radio"` explicitly.

## TypeScript Shape

```typescript
import { property } from 'lit/decorators.js';
import { MkElement, registerBase, requireInnerControl } from '@markommerce/frontend';
import '../../css/components/mk-radio.css';

export class MkRadioElement extends MkElement {
  @property({ type: String, reflect: true }) size?: 'sm' | 'base' | 'lg';

  override connectedCallback(): void {
    super.connectedCallback();
    requireInnerControl(this, 'input[type="radio"]');
  }
}

registerBase('mk-radio', MkRadioElement);
```

## CSS Shape (`mk-radio.css`)

Mirror `mk-checkbox.css` with `input[type="radio"]` selectors. Sizes and focus/disabled state are identical.

## Requirements (Test Descriptions)

- [ ] `it registers under the tag name "mk-radio"`
- [ ] `it MkRadioElement extends MkElement`
- [ ] `it the size attribute reflects between property and the DOM attribute`
- [ ] `it does not remove its light-DOM input child after upgrade`
- [ ] `it the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-radio.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- CSS uses only `--mk-*` tokens
- All CSS inside `@layer components`
- Uses `accent-color` for tinting
- Stylelint passes
- TypeScript compiles without errors
