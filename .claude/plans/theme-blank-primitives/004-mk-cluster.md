# Task 004: `mk-cluster` — Horizontal Flex-Wrap Row

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Implement the `mk-cluster` primitive: horizontal flex row with wrap, typically used for button rows, tag lists, or inline metadata. CSS-only behavior. Defaults: `display: flex; flex-wrap: wrap; gap: var(--mk-space-3); align-items: center;`.

## Context

- Files to edit:
  - `packages/theme-blank/resources/js/components/mk-cluster.ts`
  - `packages/theme-blank/resources/css/components/mk-cluster.css`
- Files to create:
  - `packages/theme-blank/resources/js/components/mk-cluster.test.ts`
  - `docs/src/content/docs/packages/theme-blank/mk-cluster.md`

### Component API

Attributes (all optional):
- `gap` — `0..9` mapped to `--mk-space-{n}`. Default: `3`.
- `align` — `start | center | end | baseline | stretch`. Default: `center`.
- `justify` — `start | center | end | between | around | evenly`. Default: `start`.

### CSS shape

```css
@layer components {
  mk-cluster {
    display: flex;
    flex-wrap: wrap;
    gap: var(--mk-space-3);
    align-items: center;
    justify-content: flex-start;
  }
  mk-cluster[gap="0"] { gap: var(--mk-space-0); }
  /* … through 9 … */
  mk-cluster[align="start"]    { align-items: flex-start; }
  mk-cluster[align="center"]   { align-items: center; }
  mk-cluster[align="end"]      { align-items: flex-end; }
  mk-cluster[align="baseline"] { align-items: baseline; }
  mk-cluster[align="stretch"]  { align-items: stretch; }
  mk-cluster[justify="start"]   { justify-content: flex-start; }
  mk-cluster[justify="center"]  { justify-content: center; }
  mk-cluster[justify="end"]     { justify-content: flex-end; }
  mk-cluster[justify="between"] { justify-content: space-between; }
  mk-cluster[justify="around"]  { justify-content: space-around; }
  mk-cluster[justify="evenly"]  { justify-content: space-evenly; }
}
```

### Docs page outline

Same structure as `mk-stack.md` but with three-attribute table and a HTML example showing a row of three labeled badges.

### Test pattern

Use the shared test pattern documented in `003-mk-stack.md` (Standard test pattern section). Substitute `mk-cluster` / `MkClusterElement` for the example tags.

## Requirements (Test Descriptions)
- [ ] `mk-cluster registers under the tag name "mk-cluster"`
- [ ] `MkClusterElement extends MkElement`
- [ ] `the gap attribute reflects between the property and the DOM attribute`
- [ ] `the align attribute reflects between the property and the DOM attribute`
- [ ] `the justify attribute reflects between the property and the DOM attribute`
- [ ] `appending mk-cluster to the DOM does not remove its light-DOM children`
- [ ] `the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-cluster.md with the required sections`

## Acceptance Criteria
- All requirements have passing tests
- Stylelint passes
- Docs page follows DOCS-STANDARDS
- `npm test` passes
- Default attribute-less rendering produces a flex-row with wrap, `var(--mk-space-3)` gap, `align-items: center`, `justify-content: flex-start`

## Implementation Notes
(Left blank — filled in by programmer during implementation)
