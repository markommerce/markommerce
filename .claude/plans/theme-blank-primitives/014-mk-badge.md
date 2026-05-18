# Task 014: `mk-badge` — Status Badge with Variants

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Implement `mk-badge`: a small inline-block badge used to display status, counts, or labels. Variants map to semantic color tokens. CSS-only behavior. Consumer provides the text/content; the badge supplies the chrome.

## Context

- Files to edit:
  - `packages/theme-blank/resources/js/components/mk-badge.ts`
  - `packages/theme-blank/resources/css/components/mk-badge.css`
- Files to create:
  - `packages/theme-blank/resources/js/components/mk-badge.test.ts`
  - `docs/src/content/docs/packages/theme-blank/mk-badge.md`

### Component API

Attributes (all optional):
- `variant` — `neutral | primary | success | warning | danger | info`. Default: `neutral`.
- `size` — `sm | base | lg`. Default: `base`.

### Semantic structure

```html
<mk-badge variant="success" size="sm">Active</mk-badge>
```

The badge text is the badge's text content — no inner element required.

### CSS shape

```css
@layer components {
  mk-badge {
    display: inline-flex;
    align-items: center;
    gap: var(--mk-space-1);
    padding-inline: var(--mk-space-2);
    padding-block: var(--mk-space-1);
    border-radius: var(--mk-radius-sm);
    font-size: var(--mk-font-size-sm);
    font-weight: var(--mk-font-weight-medium);
    line-height: 1;
    /* neutral default */
    background-color: var(--mk-color-border);
    color: var(--mk-color-fg);
  }

  mk-badge[variant="primary"] { background-color: var(--mk-color-primary);        color: var(--mk-color-on-primary); }
  mk-badge[variant="success"] { background-color: var(--mk-color-success);        color: var(--mk-color-on-primary); }
  mk-badge[variant="warning"] { background-color: var(--mk-color-warning);        color: var(--mk-color-on-primary); }
  mk-badge[variant="danger"]  { background-color: var(--mk-color-danger);         color: var(--mk-color-on-primary); }
  mk-badge[variant="info"]    { background-color: var(--mk-color-info);           color: var(--mk-color-on-primary); }
  mk-badge[variant="neutral"] { background-color: var(--mk-color-border);         color: var(--mk-color-fg); }

  mk-badge[size="sm"]   { font-size: var(--mk-font-size-xs); padding-inline: var(--mk-space-1); }
  mk-badge[size="base"] { font-size: var(--mk-font-size-sm); padding-inline: var(--mk-space-2); }
  mk-badge[size="lg"]   { font-size: var(--mk-font-size-base); padding-inline: var(--mk-space-3); }
}
```

### Docs page outline

Standard layout.

### Test pattern

Use the shared test pattern documented in `003-mk-stack.md`. Substitute `mk-badge` / `MkBadgeElement`. `mk-badge` typically has text content rather than an element child; assertions on "preserved light-DOM" should check `el.textContent` rather than `el.querySelector(...)`.

## Requirements (Test Descriptions)
- [ ] `mk-badge registers under the tag name "mk-badge"`
- [ ] `MkBadgeElement extends MkElement`
- [ ] `the variant attribute reflects between the property and the DOM attribute`
- [ ] `the size attribute reflects between the property and the DOM attribute`
- [ ] `appending mk-badge to the DOM does not remove its light-DOM text content`
- [ ] `the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-badge.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- Stylelint passes
- Docs page follows DOCS-STANDARDS
- `npm test` passes

## Implementation Notes
(Left blank — filled in by programmer during implementation)
