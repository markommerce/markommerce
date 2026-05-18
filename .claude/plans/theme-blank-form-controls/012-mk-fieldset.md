# Task 012: `mk-fieldset` — Group Wrapper for Related Fields

**Status**: completed
**Depends on**: 002, 003
**Retry count**: 0

## Description
Create `mk-fieldset` — a custom element wrapper around a native `<fieldset>` element with an optional `<legend>`. Resets browser default fieldset styles (border, padding, margin) and provides consistent spacing. The simplest form component — no JS state management, no validation logic.

## Context
- Related files:
  - New: `packages/theme-blank/resources/js/components/mk-fieldset.ts`
  - New: `packages/theme-blank/resources/css/components/mk-fieldset.css`
  - New: `packages/theme-blank/resources/js/components/mk-fieldset.test.ts`
- Patterns to follow: `mk-stack.ts` (minimal class — just attribute + register).
- `requireInnerControl` from `@markommerce/frontend` — call in `connectedCallback` with selector `'fieldset'`.

## DOM Shape

```html
<mk-fieldset>
  <fieldset>
    <legend>Shipping address</legend>
    <mk-field>…</mk-field>
    <mk-field>…</mk-field>
  </fieldset>
</mk-fieldset>
```

## TypeScript Shape

```typescript
import { MkElement, registerBase, requireInnerControl } from '@markommerce/frontend';
import '../../css/components/mk-fieldset.css';

export class MkFieldsetElement extends MkElement {
  override connectedCallback(): void {
    super.connectedCallback();
    requireInnerControl(this, 'fieldset');
  }
}

registerBase('mk-fieldset', MkFieldsetElement);
```

## CSS Shape (`mk-fieldset.css`)

```css
@layer components {
  mk-fieldset {
    display: block;
  }

  mk-fieldset > fieldset {
    border: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: var(--mk-field-gap);
  }

  mk-fieldset > fieldset > legend {
    font-weight: var(--mk-font-weight-medium);
    font-size: var(--mk-font-size-base);
    padding: 0;
    margin-block-end: var(--mk-space-2);
    float: left; /* legend flow fix — standard technique */
    width: 100%;
  }

  mk-fieldset > fieldset > legend + * {
    clear: left;
  }
}
```

## Requirements (Test Descriptions)

- [ ] `it registers under the tag name "mk-fieldset"`
- [ ] `it MkFieldsetElement extends MkElement`
- [ ] `it does not remove its light-DOM fieldset child after upgrade`
- [ ] `it the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-fieldset.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- CSS uses only `--mk-*` tokens
- All CSS inside `@layer components`
- Native `<fieldset>` border/padding/margin reset
- Stylelint passes
- TypeScript compiles without errors
