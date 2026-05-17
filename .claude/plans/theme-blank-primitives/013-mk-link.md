# Task 013: `mk-link` — Variant + Underline-Policy Link Wrapper

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Implement `mk-link`: a styled wrapper for native `<a>` elements with variant colors and underline-display policies. Consumer provides the `<a>` tag inside `mk-link`; the component styles it. CSS-only behavior. Does NOT replace anchor attributes — accessibility is fully delegated to the slotted anchor.

## Context

- Files to edit:
  - `packages/theme-blank/resources/js/components/mk-link.ts`
  - `packages/theme-blank/resources/css/components/mk-link.css`
- Files to create:
  - `packages/theme-blank/resources/js/components/mk-link.test.ts`
  - `docs/src/content/docs/packages/theme-blank/mk-link.md`

### Component API

Attributes (all optional):
- `variant` — `default | muted | danger`. Default: `default`.
- `underline` — `always | hover | never`. Default: `hover`.

### Semantic structure

```html
<mk-link variant="muted" underline="hover">
  <a href="/about">About</a>
</mk-link>
```

### CSS shape

```css
@layer components {
  mk-link {
    display: inline;
  }
  mk-link > a {
    color: var(--mk-color-primary);
    text-decoration: underline;
    text-underline-offset: 0.2em;
  }
  mk-link[variant="default"] > a { color: var(--mk-color-primary); }
  mk-link[variant="muted"]   > a { color: var(--mk-color-fg-muted); }
  mk-link[variant="danger"]  > a { color: var(--mk-color-danger); }

  mk-link[underline="always"] > a { text-decoration: underline; }
  mk-link[underline="hover"]  > a { text-decoration: none; }
  mk-link[underline="hover"]  > a:hover,
  mk-link[underline="hover"]  > a:focus-visible { text-decoration: underline; }
  mk-link[underline="never"]  > a { text-decoration: none; }
}
```

The reset to `text-decoration: none` for `underline="hover"` overrides the `@layer base` default (`a { text-decoration: underline; }`). Order matters: `mk-link` CSS is in `@layer components`, base is in `@layer base`, and components beats base.

### Docs page outline

Standard layout. Document the policy that `mk-link` does NOT inject `<a>` tags — consumers must provide them.

### Test pattern

Use the shared test pattern documented in `003-mk-stack.md`. Substitute `mk-link` / `MkLinkElement`.

## Requirements (Test Descriptions)
- [ ] `mk-link registers under the tag name "mk-link"`
- [ ] `MkLinkElement extends MkElement`
- [ ] `the variant attribute reflects between the property and the DOM attribute`
- [ ] `the underline attribute reflects between the property and the DOM attribute`
- [ ] `appending mk-link to the DOM does not remove its light-DOM anchor child`
- [ ] `the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-link.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- Stylelint passes
- Docs page follows DOCS-STANDARDS
- `npm test` passes

## Implementation Notes
(Left blank — filled in by programmer during implementation)
