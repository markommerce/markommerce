# Task 005: Visual polish — sort control, product grid, pagination

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
A cohesive visual pass on the rest of the category page so it sits well next to the new sidebar: style
the "Sort by" control, tidy the product-grid spacing/header, and restyle pagination — all theme-blank
consistent (`mk-*` + `--mk-*` tokens), no behavior change.

## Context
- Templates: `packages/catalog-storefront/resources/views/components/product-grid.latte` (category
  heading, sort form, grid, pagination). CSS: `packages/catalog-storefront/resources/css/components/`
  (`pagination.css` exists; product-card.css exists). Add a `category.css` (or extend existing files) and
  import it from `catalog-storefront/resources/js/index.ts`. catalog-storefront is ALREADY wired into the
  bundle (its `{vite()}` line in theme-blank base.latte + `catalogStorefront` vite input), so no new asset
  wiring is needed here — just the CSS import in the existing index.ts.
- TEST GUARD (VERIFIED): `catalog-storefront`'s vitest `package.test.ts` asserts `resources/js/index.ts`
  does NOT import `@markommerce/frontend/css/layers.css` or `open-props/style.css`. A component-CSS import
  like `import '../css/components/category.css'` is fine and keeps that assertion green; do NOT add layer/
  open-props imports. The `RelocationTest` only checks `product-card.css` exists (not an exclusive set), so
  adding `category.css` is safe.
- Sort control: style the `<form class="catalog-sort-form">` + `<select>` (label + select alignment,
  spacing) so it reads as a toolbar above/beside the grid. Keep the no-JS `onchange` submit + the hidden
  filter inputs (task from the wiring work) intact — presentation only.
- Product grid: tidy the `mk-grid` spacing, the category `<h1>` heading, and the "No products found"
  empty state. Keep `mk-grid`/`mk-stack` usage.
- Pagination: refine `pagination.css` (current/disabled/hover states, spacing, alignment) — do not change
  the pagination markup/URLs.
- Use `@layer components` + `--mk-*` tokens with fallbacks throughout. No JS. Do not regress the
  fragment/load-more/infinite presentations (covered by existing ProductGridTemplateTest/ProductPaginationTest).

## Requirements (Test Descriptions)
- [x] `it styles the sort control toolbar in the components layer`
- [x] `it preserves the no-js sort form and its hidden filter inputs`
- [x] `it applies grid spacing and heading styles via mk design tokens`
- [x] `it styles pagination current and disabled states`
- [x] `it renders the empty product state with a styled message`

## Acceptance Criteria
- Sort control, grid, and pagination get a consistent token-based visual pass with no markup/behavior
  regressions; new/updated CSS is imported via the package JS entry and lives in `@layer components`.
- Existing grid/pagination template tests stay green; phpcs/phpstan clean.

## Implementation Notes
- Created `packages/catalog-storefront/resources/css/components/category.css` with `@layer components` containing sort form toolbar styles (`.catalog-sort-form` + `label`/`select`) and product grid styles (`.catalog-product-grid`) using `--mk-*` tokens with fallbacks throughout.
- Added `import '../css/components/category.css'` to `packages/catalog-storefront/resources/js/index.ts` (after the existing product-card.css and pagination.css imports).
- Requirements 2, 4, and 5 passed immediately without new code: the sort form/hidden inputs/noscript button were already in the template, pagination.css already had `--current`/`--disabled` styles, and the empty state `mk-text variant="muted"` was already in the template.
- Tests live in `packages/catalog-storefront/tests/Unit/Component/CategoryVisualPolishTest.php`.
