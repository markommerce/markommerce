# Task 012: `mk-text` — Single-Tag Body Text

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Implement `mk-text`: a single-tag body-text primitive with semantic visual variants (`body`, `lead`, `small`, `muted`) and weight overrides. Takes text content directly — no inner element. CSS-only behavior. No ARIA role injection needed (paragraphs are presentational; the visual variants do not change semantics). Like `mk-heading`, the component ALSO supports a legacy two-tag form for consumers who want to keep a native `<p>`/`<span>` inside (e.g., for crawler-friendly markup or to nest mixed-content like `<em>` with no `mk-text` re-wrapping).

## Context

- Files to edit (stubs from task 002):
  - `packages/theme-blank/resources/js/components/mk-text.ts`
  - `packages/theme-blank/resources/css/components/mk-text.css`
- Files to create:
  - `packages/theme-blank/resources/js/components/mk-text.test.ts`
  - `docs/src/content/docs/packages/theme-blank/mk-text.md`

### Component API

Attributes (all optional):
- `variant` — `body | lead | small | muted`. Default: `body`.
- `weight` — `normal | medium | bold`. Default: `normal`.

### Usage

Single-tag (preferred):

```html
<mk-text variant="lead">This is a leading paragraph that introduces the section.</mk-text>
```

Two-tag (legacy, for nested-element compatibility):

```html
<mk-text variant="lead">
  <p>This is a leading paragraph with <em>emphasis</em>.</p>
</mk-text>
```

### CSS shape

```css
@layer components {
  /* Single-tag: style the host directly */
  mk-text {
    display: block;
    font-size: var(--mk-font-size-base);
    font-weight: var(--mk-font-weight-normal);
    line-height: var(--mk-line-height-normal);
    color: var(--mk-color-fg);
    margin: 0;
  }
  mk-text[variant="body"]  { font-size: var(--mk-font-size-base); }
  mk-text[variant="lead"]  { font-size: var(--mk-font-size-lg); line-height: var(--mk-line-height-loose); }
  mk-text[variant="small"] { font-size: var(--mk-font-size-sm); }
  mk-text[variant="muted"] { color: var(--mk-color-fg-muted); }
  mk-text[weight="normal"] { font-weight: var(--mk-font-weight-normal); }
  mk-text[weight="medium"] { font-weight: var(--mk-font-weight-medium); }
  mk-text[weight="bold"]   { font-weight: var(--mk-font-weight-bold); }

  /* Two-tag (legacy): when a child element is present, reset host styles and let inner element inherit */
  mk-text:has(> p, > span, > div) {
    font-size: inherit;
    font-weight: inherit;
    line-height: inherit;
  }
  mk-text > p,
  mk-text > span,
  mk-text > div {
    font-size: inherit;
    font-weight: inherit;
    line-height: inherit;
    color: inherit;
    margin: 0;
  }
}
```

The `:has()` selector resets host typography when a `<p>`/`<span>`/`<div>` is present. The variant + weight attribute selectors target the host (`mk-text`), and inner elements inherit. This means both forms produce identical styled output.

The bare-tag `display: block` rule is critical — custom elements default to `display: inline`, so without this rule the unupgraded `<mk-text>` would render as inline and a post-upgrade flip to block would cause CLS.

### TS shape

```typescript
import { property } from 'lit/decorators.js';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-text.css';

export class MkTextElement extends MkElement {
  @property({ type: String, reflect: true }) variant?: 'body' | 'lead' | 'small' | 'muted';
  @property({ type: String, reflect: true }) weight?: 'normal' | 'medium' | 'bold';
}

registerBase('mk-text', MkTextElement);
```

No `connectedCallback` injection — text is presentational, no ARIA needed.

### Docs page outline

Standard layout. Document both single-tag and two-tag forms and when to choose each. Note: single-tag is the preferred form; two-tag is for nested-content compatibility.

### Test pattern

Use the shared test pattern documented in `003-mk-stack.md`. Substitute `mk-text` / `MkTextElement`.

## Requirements (Test Descriptions)
- [ ] `mk-text registers under the tag name "mk-text"`
- [ ] `MkTextElement extends MkElement`
- [ ] `the variant attribute reflects between the property and the DOM attribute`
- [ ] `the weight attribute reflects between the property and the DOM attribute`
- [ ] `appending <mk-text>Hello</mk-text> to the DOM preserves the text node child unchanged`
- [ ] `appending <mk-text><p>Hello</p></mk-text> to the DOM preserves the inner <p> child unchanged`
- [ ] `the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-text.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- Stylelint passes
- Docs page follows DOCS-STANDARDS
- `npm test` passes
- Both single-tag and two-tag forms produce visually identical output for the same `variant`/`weight` settings (verified visually in the demo route, task 016)

## Implementation Notes
(Left blank — filled in by programmer during implementation)
