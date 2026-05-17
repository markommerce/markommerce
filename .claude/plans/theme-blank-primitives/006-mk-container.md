# Task 006: `mk-container` — Max-Width Content Container

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Implement `mk-container`: a max-width content container with auto inline margins, used to keep page content from spanning ultra-wide viewports. CSS-only behavior. Default behavior: `display: block; max-width: 80rem; margin-inline: auto; padding-inline: var(--mk-space-4);`.

## Context

- Files to edit:
  - `packages/theme-blank/resources/js/components/mk-container.ts`
  - `packages/theme-blank/resources/css/components/mk-container.css`
- Files to create:
  - `packages/theme-blank/resources/js/components/mk-container.test.ts`
  - `docs/src/content/docs/packages/theme-blank/mk-container.md`

### Component API

Attributes (all optional):
- `size` — `sm | md | lg | xl | full`. Default: `lg`. Maps to specific max-width values via attribute selectors. `full` removes the max-width entirely.

### CSS shape

```css
@layer components {
  mk-container {
    display: block;
    margin-inline: auto;
    padding-inline: var(--mk-space-4);
    max-width: 80rem; /* lg default */
  }
  mk-container[size="sm"]   { max-width: 40rem; }
  mk-container[size="md"]   { max-width: 60rem; }
  mk-container[size="lg"]   { max-width: 80rem; }
  mk-container[size="xl"]   { max-width: 96rem; }
  mk-container[size="full"] { max-width: none; }
}
```

### Docs page outline

Standard layout with single-attribute table for `size`. Include the rationale ("use one container per page section; nest containers only when you need separately-sized content blocks").

### Test pattern

Use the shared test pattern documented in `003-mk-stack.md`. Substitute `mk-container` / `MkContainerElement`.

## Requirements (Test Descriptions)
- [ ] `mk-container registers under the tag name "mk-container"`
- [ ] `MkContainerElement extends MkElement`
- [ ] `the size attribute reflects between the property and the DOM attribute`
- [ ] `appending mk-container to the DOM does not remove its light-DOM children`
- [ ] `the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-container.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- Stylelint passes
- Docs page follows DOCS-STANDARDS
- `npm test` passes
- Default attribute-less rendering produces a block element with 80rem max-width, auto inline margins, and `var(--mk-space-4)` inline padding

## Implementation Notes
(Left blank — filled in by programmer during implementation)
