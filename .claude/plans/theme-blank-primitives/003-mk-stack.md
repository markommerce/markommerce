# Task 003: `mk-stack` — Vertical Rhythm Primitive

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Implement the `mk-stack` primitive: a custom element that stacks children vertically with a consistent `gap`. CSS-only behavior — the JS class is the scaffold-stub from task 002 with optional reflected attributes added. All visual behavior is keyed off attributes via `[attribute="value"]` selectors. Defaults: `display: flex; flex-direction: column; gap: var(--mk-space-3);`.

## Context

- Files to edit (created as stubs in task 002):
  - `packages/theme-blank/resources/js/components/mk-stack.ts` — add reflected `gap` and `align` attributes
  - `packages/theme-blank/resources/css/components/mk-stack.css` — fill in the styles
- Files to create:
  - `packages/theme-blank/resources/js/components/mk-stack.test.ts` — Vitest unit test
  - `docs/src/content/docs/packages/theme-blank/mk-stack.md` — per-component docs page

### Component API

Attributes (all optional):
- `gap` — token scale `0` through `9`, mapped to `--mk-space-{n}`. Default: `3`.
- `align` — `start | center | end | stretch`. Default: `stretch` (children fill the cross axis).

### CSS shape

```css
@layer components {
  mk-stack {
    display: flex;
    flex-direction: column;
    gap: var(--mk-space-3);
  }
  mk-stack[gap="0"] { gap: var(--mk-space-0); }
  mk-stack[gap="1"] { gap: var(--mk-space-1); }
  /* … through 9 … */
  mk-stack[align="start"]   { align-items: flex-start; }
  mk-stack[align="center"]  { align-items: center; }
  mk-stack[align="end"]     { align-items: flex-end; }
  mk-stack[align="stretch"] { align-items: stretch; }
}
```

### TS shape

```typescript
import { property } from 'lit/decorators.js';
import { MkElement } from '@markommerce/frontend';
import { registerBase } from '@markommerce/frontend';
import '../../css/components/mk-stack.css';

export class MkStackElement extends MkElement {
  @property({ type: String, reflect: true }) gap?: string;
  @property({ type: String, reflect: true }) align?: 'start' | 'center' | 'end' | 'stretch';
}

registerBase('mk-stack', MkStackElement);
```

### Docs page outline (`mk-stack.md`)

Sections in order:
1. Frontmatter (`title: mk-stack`, `description: …`)
2. Intro paragraph
3. `## HTML Usage` — `<mk-stack gap="4">…</mk-stack>` example
4. `## Attributes` — table: `gap`, `align`
5. `## Slots` — default slot
6. `## Events` — "Emits no events."
7. `## CSS Custom Properties` — list `--mk-space-*` consumed
8. `## Variants & States` — combinations of `gap` and `align`
9. `## Extending` — CSS override example (`@layer theme { mk-stack { padding: var(--mk-space-2); } }`)
10. `## Accessibility` — note that the element is presentational

## Requirements (Test Descriptions)
- [ ] `mk-stack registers under the tag name "mk-stack"` (assert via `getRegisteredComponents()` from `@markommerce/frontend`)
- [ ] `MkStackElement extends MkElement`
- [ ] `the gap attribute reflects between the property and the DOM attribute`
- [ ] `the align attribute reflects between the property and the DOM attribute`
- [ ] `after defineAllComponents() and parsing <mk-stack><span>preserved</span></mk-stack>, the upgraded element still contains the span child (assert via querySelector after awaiting updateComplete)`
- [ ] `the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-stack.md with the required sections`

### Standard test pattern (shared by tasks 003-014)

Every per-component test follows the same shape — codify it once here so all subsequent component tasks copy this pattern:

```typescript
// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-stack'; // side-effect: calls registerBase('mk-stack', MkStackElement)
import { MkStackElement } from './mk-stack';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-stack')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-stack', () => {
  it('registers under the tag name "mk-stack"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-stack');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkStackElement);
  });

  it('MkStackElement extends MkElement', () => {
    expect(new MkStackElement()).toBeInstanceOf(MkElement);
  });

  // ...
});
```

Notes: `defineAllComponents()` is module-level idempotent (sets a `defined` flag), so it can be called from beforeAll across multiple component test files without issue. The `customElements.get()` guard prevents re-entry within a single file. The per-task assertions on attribute reflection use `el.setAttribute()` followed by `await el.updateComplete` then `expect(el.gap).toBe(...)` (and vice versa for property → attribute).

## Acceptance Criteria
- All requirements have passing tests
- Stylelint passes on the CSS file
- Docs page follows `docs/DOCS-STANDARDS.md` formatting rules (no `## Overview` heading, em dashes for parentheticals, frontmatter present, root-relative links)
- The CSS file is inside `@layer components`
- `npm test` passes
- The default attribute-less rendering of `<mk-stack>` produces a flex-column with `var(--mk-space-3)` gap and `align-items: stretch`

## Implementation Notes
(Left blank — filled in by programmer during implementation)
