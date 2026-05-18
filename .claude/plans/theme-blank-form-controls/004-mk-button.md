# Task 004: `mk-button` — Variant/Size/Loading Form Button

**Status**: completed
**Depends on**: 002, 003
**Retry count**: 0

## Description
Create `mk-button` — a custom element wrapper around a native `<button>`. The wrapper adds visual variants (`primary`, `secondary`, `ghost`, `danger`), sizes (`sm`, `base`, `lg`), and a `loading` attribute that disables the inner button and sets `aria-busy` on the wrapper. All styling is CSS-driven; the JS class registers the tag and manages the `loading` side-effect.

## Context
- Related files (stubs scaffolded by task 003, expand them here):
  - `packages/theme-blank/resources/js/components/mk-button.ts` (stub from 003 — extend)
  - `packages/theme-blank/resources/css/components/mk-button.css` (stub from 003 — fill in)
  - New: `packages/theme-blank/resources/js/components/mk-button.test.ts`
- Patterns to follow: `mk-badge.ts` (attribute reflection pattern), `mk-heading.ts` (`connectedCallback` for imperative DOM mutations).
- `requireInnerControl` from `@markommerce/frontend` — call in `connectedCallback` with selector `'button'`.

## TypeScript Shape

```typescript
// mk-button.ts
import { property } from 'lit/decorators.js';
import { MkElement, registerBase, requireInnerControl } from '@markommerce/frontend';
import '../../css/components/mk-button.css';

export class MkButtonElement extends MkElement {
  @property({ type: String, reflect: true }) variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
  @property({ type: String, reflect: true }) size?: 'sm' | 'base' | 'lg';
  @property({ type: Boolean, reflect: true }) loading = false;

  override connectedCallback(): void {
    super.connectedCallback();
    requireInnerControl(this, 'button');
    this.#syncLoading();
  }

  override updated(changed: Map<string, unknown>): void {
    if (changed.has('loading')) this.#syncLoading();
  }

  #syncLoading(): void {
    const btn = this.querySelector('button');
    if (!btn) return;
    if (this.loading) {
      btn.disabled = true;
      this.setAttribute('aria-busy', 'true');
    } else {
      btn.disabled = false;
      this.removeAttribute('aria-busy');
    }
  }
}

registerBase('mk-button', MkButtonElement);
```

## CSS Shape (`mk-button.css`)

```css
@layer components {
  mk-button {
    display: inline-flex;
  }

  mk-button > button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--mk-space-2);
    padding-inline: var(--mk-space-4);
    padding-block: var(--mk-space-2);
    border-radius: var(--mk-button-radius);
    font-weight: var(--mk-button-font-weight);
    font-size: var(--mk-font-size-base);
    border: 1px solid transparent;
    cursor: pointer;
    transition: background-color var(--mk-transition-fast), color var(--mk-transition-fast), border-color var(--mk-transition-fast);

    /* default = secondary */
    background-color: transparent;
    color: var(--mk-color-fg);
    border-color: var(--mk-color-border);
  }

  /* variants */
  mk-button[variant="primary"] > button { … }
  mk-button[variant="ghost"] > button { … }
  mk-button[variant="danger"] > button { … }

  /* sizes */
  mk-button[size="sm"] > button { … }
  mk-button[size="lg"] > button { … }

  /* states via :has() */
  mk-button:has(button:disabled) { opacity: 0.5; cursor: not-allowed; }
  mk-button:has(button:focus-visible) > button { outline: 2px solid var(--mk-color-focus-ring); outline-offset: 2px; }
}
```

Fill in the variant/size/state rules with appropriate tokens. Do not use hardcoded color values — reference `--mk-*` tokens only.

## Requirements (Test Descriptions)

- [ ] `it registers under the tag name "mk-button"`
- [ ] `it MkButtonElement extends MkElement`
- [ ] `it the variant attribute reflects between property and the DOM attribute`
- [ ] `it the size attribute reflects between property and the DOM attribute`
- [ ] `it the loading attribute reflects between property and the DOM attribute`
- [ ] `it sets disabled on the inner button element when loading is set to true`
- [ ] `it sets aria-busy="true" on the mk-button wrapper when loading is set to true`
- [ ] `it removes disabled from the inner button element when loading is set back to false`
- [ ] `it removes aria-busy from the mk-button wrapper when loading is set back to false`
- [ ] `it does not remove its light-DOM button child after upgrade`
- [ ] `it the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-button.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- CSS uses only `--mk-*` tokens (no hardcoded colors/sizes)
- All CSS inside `@layer components`
- Stylelint passes
- TypeScript compiles without errors
