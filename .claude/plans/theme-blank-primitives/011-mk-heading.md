# Task 011: `mk-heading` — Single-Tag Heading with ARIA

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Implement `mk-heading`: a single-tag heading primitive that takes text content directly and decouples semantic level (`level="1".."6"`) from visual size (`size="xs".."3xl"`). Auto-injects `role="heading"` and `aria-level="<level>"` on the host element in `connectedCallback` (synchronous, pre-paint, CLS-safe). The text is a child text node from initial parse, so there is no DOM mutation at upgrade time and CLS is zero by construction.

## Context

- Files to edit (stubs from task 002):
  - `packages/theme-blank/resources/js/components/mk-heading.ts`
  - `packages/theme-blank/resources/css/components/mk-heading.css`
- Files to create:
  - `packages/theme-blank/resources/js/components/mk-heading.test.ts`
  - `docs/src/content/docs/packages/theme-blank/mk-heading.md`

### Component API

Attributes (all optional):
- `level` — `1 | 2 | 3 | 4 | 5 | 6`. Semantic heading level. Default: `2`. Synced to `aria-level` on the host in `connectedCallback`. If `level` is missing or invalid, no `aria-level` is set and the default browser-assigned heading role/level applies (which for our custom element is *none* — see SEO caveat below).
- `size` — visual size: `xs | sm | base | lg | xl | 2xl | 3xl`. Default: `xl`. Maps to `--mk-font-size-*` tokens.
- `weight` — `normal | medium | bold`. Default: `bold`.

### Usage

```html
<mk-heading level="2" size="lg">About us</mk-heading>
```

The component takes text content as the direct child of the host — NOT a nested `<h2>`. The host element itself receives `role="heading"` and `aria-level="2"`, which is the WAI-ARIA equivalent of `<h2>`. CSS styles `mk-heading` directly (the host is the styled box).

### CSS shape

```css
@layer components {
  mk-heading {
    display: block;
    font-size: var(--mk-font-size-xl);
    font-weight: var(--mk-font-weight-bold);
    line-height: var(--mk-line-height-tight);
    margin: 0;
  }
  mk-heading[size="xs"]   { font-size: var(--mk-font-size-xs); }
  mk-heading[size="sm"]   { font-size: var(--mk-font-size-sm); }
  mk-heading[size="base"] { font-size: var(--mk-font-size-base); }
  mk-heading[size="lg"]   { font-size: var(--mk-font-size-lg); }
  mk-heading[size="xl"]   { font-size: var(--mk-font-size-xl); }
  mk-heading[size="2xl"]  { font-size: var(--mk-font-size-2xl); }
  mk-heading[size="3xl"]  { font-size: var(--mk-font-size-3xl); }
  mk-heading[weight="normal"] { font-weight: var(--mk-font-weight-normal); }
  mk-heading[weight="medium"] { font-weight: var(--mk-font-weight-medium); }
  mk-heading[weight="bold"]   { font-weight: var(--mk-font-weight-bold); }
}
```

The bare-tag rule (`mk-heading { display: block; ... }`) is what makes the unupgraded element render correctly — the browser's user-agent stylesheet does NOT give custom elements `display: block` by default (they're `display: inline`), so we explicitly set it. This is the CLS-prevention mechanism (same as for layout primitives).

### TS shape

```typescript
import { property } from 'lit/decorators.js';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-heading.css';

export class MkHeadingElement extends MkElement {
  @property({ type: String, reflect: true }) level?: '1' | '2' | '3' | '4' | '5' | '6';
  @property({ type: String, reflect: true }) size?: 'xs' | 'sm' | 'base' | 'lg' | 'xl' | '2xl' | '3xl';
  @property({ type: String, reflect: true }) weight?: 'normal' | 'medium' | 'bold';

  override connectedCallback(): void {
    // Sync ARIA role + level synchronously, BEFORE Lit's connectedCallback,
    // so the host has the correct role at first paint. CLS-safe because
    // attribute writes do not reflow the box.
    if (!this.hasAttribute('role')) {
      this.setAttribute('role', 'heading');
    }
    const levelAttr = this.getAttribute('level');
    if (levelAttr !== null && /^[1-6]$/.test(levelAttr) && !this.hasAttribute('aria-level')) {
      this.setAttribute('aria-level', levelAttr);
    }
    super.connectedCallback();
  }
}

registerBase('mk-heading', MkHeadingElement);
```

The component does NOT override an existing `role` or `aria-level` set by the consumer — this is important for consumers who want to deviate (e.g., set `role="presentation"` for a visual-only heading).

### SEO caveat (document on the docs page)

`<mk-heading>` with `role="heading"` + `aria-level` is recognized by screen readers as a heading and by Google's renderer (which respects ARIA semantics). However, native `<h1>`-`<h6>` tags remain the gold standard for SEO outline extraction. For SEO-critical content — product names, article H1s, primary page titles — consumers may prefer to wrap a native heading inside `mk-heading` using the legacy two-tag form:

```html
<mk-heading size="lg">
  <h2>SEO-critical heading</h2>
</mk-heading>
```

This still works because the CSS rule `mk-heading > *` is preserved as a secondary selector. The component does NOT auto-inject `aria-level` when it has a child heading element (it detects this via the presence of an `h1`-`h6` first-element child).

**CSS for the legacy two-tag form:**

```css
@layer components {
  /* If a child heading element is present, defer to its semantics */
  mk-heading:has(> h1, > h2, > h3, > h4, > h5, > h6) {
    font-size: inherit;
    font-weight: inherit;
    line-height: inherit;
  }
  mk-heading > h1,
  mk-heading > h2,
  mk-heading > h3,
  mk-heading > h4,
  mk-heading > h5,
  mk-heading > h6 {
    font-size: inherit;
    font-weight: inherit;
    line-height: inherit;
    margin: 0;
  }
}
```

The `:has()` selector resets the host's typography styles when a real heading element is inside, so the inner element controls everything. This means a consumer can switch between single-tag and two-tag forms at the markup level with no JS or CSS changes needed. `:has()` is universally supported (Chromium 105+, Firefox 121+, Safari 15.4+).

### TS detection of inner heading

In `connectedCallback`, skip ARIA injection if a child heading is present:

```typescript
override connectedCallback(): void {
  const hasInnerHeading = this.querySelector(':scope > h1, :scope > h2, :scope > h3, :scope > h4, :scope > h5, :scope > h6') !== null;
  if (!hasInnerHeading) {
    if (!this.hasAttribute('role')) {
      this.setAttribute('role', 'heading');
    }
    const levelAttr = this.getAttribute('level');
    if (levelAttr !== null && /^[1-6]$/.test(levelAttr) && !this.hasAttribute('aria-level')) {
      this.setAttribute('aria-level', levelAttr);
    }
  }
  super.connectedCallback();
}
```

### Docs page outline

Standard layout. Document both usage forms (single-tag and two-tag legacy) and explain the SEO trade-off. Emphasize that single-tag is the preferred form for most cases and that two-tag exists for SEO-critical content.

### Test pattern

Use the shared test pattern documented in `003-mk-stack.md`. Substitute `mk-heading` / `MkHeadingElement`.

## Requirements (Test Descriptions)
- [ ] `mk-heading registers under the tag name "mk-heading"`
- [ ] `MkHeadingElement extends MkElement`
- [ ] `the level attribute reflects between the property and the DOM attribute`
- [ ] `the size attribute reflects between the property and the DOM attribute`
- [ ] `the weight attribute reflects between the property and the DOM attribute`
- [ ] `parsing <mk-heading level="2">Hello</mk-heading> and upgrading sets role="heading" and aria-level="2" synchronously during connectedCallback — assert immediately after defineAllComponents() returns, without awaiting updateComplete`
- [ ] `parsing <mk-heading><h2>Hello</h2></mk-heading> does NOT set role or aria-level on the host (defers to the inner heading element)`
- [ ] `the component does not override an existing role attribute set by the consumer`
- [ ] `the component does not override an existing aria-level attribute set by the consumer`
- [ ] `appending mk-heading to the DOM does not remove its light-DOM text content`
- [ ] `the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-heading.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- Stylelint passes
- Docs page follows DOCS-STANDARDS
- `npm test` passes
- The component works in both single-tag and two-tag forms with no JS changes needed by the consumer

## Implementation Notes
(Left blank — filled in by programmer during implementation)
