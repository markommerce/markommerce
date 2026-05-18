# Task 016: Docs Pages (10 Components) + Index Form Controls Section

**Status**: completed
**Depends on**: 004, 005, 006, 007, 008, 009, 010, 011, 012, 013
**Retry count**: 0

## Description
Create 10 per-component docs pages in `docs/src/content/docs/packages/theme-blank/` and update the docs index page with a new `## Form Controls` section linking to all 10. Follow the same per-component template used for Phase 2 primitives.

## Context
- Related files:
  - New docs pages: `docs/src/content/docs/packages/theme-blank/mk-{name}.md` for all 10 components
  - Existing index: `docs/src/content/docs/packages/theme-blank/index.md`
  - Reference template: any existing Phase 2 primitive docs page (e.g., `mk-badge.md`, `mk-link.md`)
- Required sections per page (per the docs template in `_plan.md` Architecture Notes of the Phase 2 plan):
  1. Intro paragraph (no `## Overview` heading)
  2. `## Installation` (consistent with Phase 2 format)
  3. `## Usage` with HTML example
  4. `## API Reference` with attributes table + slots + events + CSS custom properties
  5. Accessibility notes for form controls are especially important (label association, ARIA, keyboard)

## Per-Component Docs Summary

### `mk-button`
- Attributes: `variant` (primary/secondary/ghost/danger), `size` (sm/base/lg), `loading` (boolean)
- Events: none emitted — native button handles click
- Accessibility: `loading` sets `aria-busy`; disabled state via inner `<button disabled>`

### `mk-input`
- Attributes: `variant` (outline/filled), `size` (sm/base/lg)
- CSS custom properties: reads `--mk-input-bg`, `--mk-input-border-color`, `--mk-input-border-color-focus`, `--mk-input-border-color-error`, `--mk-radius-input`, `--mk-color-focus-ring`
- States documented: focus, disabled (`:has(input:disabled)`), invalid (`:user-invalid`), valid (`:user-valid`)

### `mk-textarea`
- Same as mk-input; note `min-height` instead of `height`, `resize: vertical`

### `mk-select`
- Same as mk-input; note custom arrow via `background-image`, `appearance: none`

### `mk-checkbox`
- Attributes: `size` (sm/base/lg)
- Note: uses `accent-color` for tinting — inherits OS checkbox appearance otherwise

### `mk-radio`
- Attributes: `size` (sm/base/lg)
- Note: same as mk-checkbox; group via native `name` attribute on inner inputs

### `mk-switch`
- Attributes: `size` (sm/base/lg)
- Note: `connectedCallback` sets `role="switch"` on inner input; custom toggle-pill via CSS
- Accessibility: ARIA role="switch" + checked state communicated natively

### `mk-field`
- Attributes: none (data-state/data-touched are internal)
- Events: none emitted directly (validation state changes are CSS-observable via `data-state`)
- JS API: `addValidator(name, fn)`, `addAsyncValidator(name, fn)`, `validate()` → `Promise<boolean>`
- Slots: default (label, control, hint, `[data-mk-error]`)
- Browser baseline: `:user-invalid` requires Chrome 119+, FF 88+, Safari 16.4+

### `mk-fieldset`
- Attributes: none
- Note: resets native fieldset border/padding/margin

### `mk-form`
- Attributes: none
- Events: `mk-submit` (detail: `{ formData: FormData }`), `mk-invalid` (detail: `{ fields: MkFieldElement[] }`)
- Note: sets `novalidate` on inner `<form>`; coordinates `mk-field.validate()` in parallel

## Requirements (Test Descriptions)

Tests use file-content assertions (in the component's `.test.ts` or a dedicated `docs.test.ts`):

- [ ] `it the mk-button documentation page exists with required sections`
- [ ] `it the mk-input documentation page exists with required sections`
- [ ] `it the mk-textarea documentation page exists with required sections`
- [ ] `it the mk-select documentation page exists with required sections`
- [ ] `it the mk-checkbox documentation page exists with required sections`
- [ ] `it the mk-radio documentation page exists with required sections`
- [ ] `it the mk-switch documentation page exists with required sections`
- [ ] `it the mk-field documentation page exists with required sections`
- [ ] `it the mk-fieldset documentation page exists with required sections`
- [ ] `it the mk-form documentation page exists with required sections`
- [ ] `it the docs index page contains a Form Controls section with links to all 10 components`

## Acceptance Criteria
- All 10 docs pages exist with frontmatter `title:` and `description:` fields
- All 10 pages have `## Installation`, `## Usage`, `## API Reference` sections
- `mk-field` and `mk-form` pages include the JS API methods with code examples
- `mk-switch` accessibility notes cover `role="switch"` and the server-side guard
- Docs index `## Form Controls` section links to all 10 with one-line descriptions
- No broken internal links
