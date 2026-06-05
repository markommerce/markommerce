# Task 006: CSS — style the Load-previous button

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Style the "Load previous" button (and ensure the "Load more" button) so they match the existing styled pagination action button, positioned above the grid (previous) / below (more).

## Context
- File: `packages/catalog-storefront/resources/css/components/pagination.css` (extend; it already styles `mk-load-more:defined button` / `mk-infinite-scroll:defined button` and hides the numbered bar when `:defined`).
- The Load-previous button uses class `catalog-pagination__load-previous` and `data-role="load-previous"` (from Task 002). Style it like the existing action button (centered, accent or secondary, hover/focus, using theme tokens `--mk-color-primary`, `--mk-radius-base`, `--mk-space-*`, `--mk-color-focus-ring`).
- **Match Task 002's pinned DOM structure.** The grid (`<mk-grid class="catalog-product-grid">`) is a SIBLING that renders ABOVE the `mk-load-more`/`mk-infinite-scroll` element, not inside it. So a Load-previous button that is a child of the `mk-*` element is physically BELOW the grid, not above it. Read Task 002's Implementation Notes for the chosen structure (button kept inside `mk-*` + CSS repositioning, OR button moved before `<mk-grid>`) and style accordingly. Do not assume "the template renders it first" — verify against what Task 002 actually shipped.
- Keep it visually consistent with `.catalog-pagination__link`/the existing styled button; consider a subtler/secondary treatment for "Load previous" vs the primary "Load more" if it reads better.
- Only style buttons within `:defined` elements (so no-JS render is unaffected).

## Requirements (Test Descriptions)
- [ ] `the pagination stylesheet styles the load-previous button`
- [ ] `the load-previous button styling is scoped to defined custom elements`

## Acceptance Criteria
- The Load-previous (and Load-more) buttons are styled consistently with the pagination UI and positioned correctly (previous above the grid).
- Verified by a CSS-content assertion test and/or a render check; visual confirmation in the playground.
- All requirements have passing tests.

## Implementation Notes
(CSS task — back the requirements with a small content assertion test, or fold into the storefront test suite. Visual confirmation happens in the playground dev server.)
