# Task 001: Add Form Design Tokens

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add form-specific design tokens to `packages/theme-blank/resources/css/tokens.css` inside the existing `@layer tokens` / `:root` block. These tokens are consumed by all 10 form component CSS files in tasks 004–013.

## Context
- Related files: `packages/theme-blank/resources/css/tokens.css`
- Existing tokens already cover colors, spacing, typography, radius, shadow, motion — all in `@layer tokens { :root { … } }`.
- Dark-mode overrides live in `[data-theme="dark"] { … }` inside the same `@layer tokens` block.
- Patterns to follow: existing `--mk-*` token naming; use Open Props variables as values where an equivalent exists.

## Tokens to Add

**Focus ring** (`:focus-visible` outlines across all interactive form elements):
- `--mk-color-focus-ring`: `var(--blue-5)`

**Input geometry:**
- `--mk-radius-input`: `var(--radius-2)` (same as `--mk-radius-sm`)
- `--mk-input-height-sm`: `var(--size-7)`
- `--mk-input-height-base`: `var(--size-8)`
- `--mk-input-height-lg`: `var(--size-9)`

**Input surface (shared by mk-input, mk-textarea, mk-select):**
- `--mk-input-bg`: `var(--mk-color-surface)`
- `--mk-input-border-color`: `var(--mk-color-border)`
- `--mk-input-border-color-focus`: `var(--mk-color-primary)`
- `--mk-input-border-color-error`: `var(--mk-color-danger)`
- `--mk-input-color`: `var(--mk-color-fg)`
- `--mk-input-color-placeholder`: `var(--mk-color-fg-muted)`
- `--mk-input-padding-inline`: `var(--mk-space-3)`

**Button surface:**
- `--mk-button-radius`: `var(--mk-radius-base)`
- `--mk-button-font-weight`: `var(--mk-font-weight-medium)`

**Field layout:**
- `--mk-field-gap`: `var(--mk-space-1)` (spacing between label, control, hint, error)
- `--mk-field-error-color`: `var(--mk-color-danger)`
- `--mk-field-hint-color`: `var(--mk-color-fg-muted)`

**Dark-mode overrides to add inside `[data-theme="dark"]`:**
- `--mk-input-bg`: `var(--gray-8)`
- `--mk-input-border-color`: `var(--gray-6)`
- `--mk-color-focus-ring`: `var(--blue-4)`

## Requirements (Test Descriptions)

- [ ] `it adds --mk-color-focus-ring to the :root block in @layer tokens`
- [ ] `it adds --mk-radius-input to the :root block`
- [ ] `it adds --mk-input-height-sm, --mk-input-height-base, and --mk-input-height-lg to the :root block`
- [ ] `it adds --mk-input-bg, --mk-input-border-color, and --mk-input-border-color-focus to the :root block`
- [ ] `it adds --mk-button-radius and --mk-button-font-weight to the :root block`
- [ ] `it adds --mk-field-gap, --mk-field-error-color, and --mk-field-hint-color to the :root block`
- [ ] `it adds dark-mode overrides for --mk-input-bg, --mk-input-border-color, and --mk-color-focus-ring inside [data-theme="dark"]`

These are file-content assertions in the existing `tokens.test.ts` / `tokens-expanded.test.ts` test suite (check what exists before adding tests).

## Acceptance Criteria
- All requirements have passing tests
- Tokens follow `--mk-*` naming convention
- All tokens are inside `@layer tokens { :root { … } }` (not at top level)
- Dark-mode overrides inside `[data-theme="dark"]` block
- No existing tokens removed or renamed
- Stylelint passes
