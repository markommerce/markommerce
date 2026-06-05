# Task 014: Numbered pagination Latte component

**Status**: pending
**Depends on**: 013
**Retry count**: 0

## Description
Create the numbered pagination Latte partial: crawlable `1 2 3 … prev/next` links with a "Page X of Y" label, marking the current page and hiding prev/next at the boundaries. Pure server-rendered `<a href>` — the SEO backbone every presentation mode reuses.

## Context
- Files: `packages/catalog-storefront/resources/views/components/product-pagination.latte` (+ any small data already provided on `ProductGridData` from Task 013).
- Render real `<a href="?page=N">` anchors built from `ProductGridData` page-link URLs (size/sort preserved).
- Mark the current page (e.g. `aria-current="page"`); omit/disable previous on page 1 and next on the last page.
- Use existing `mk-` markup conventions seen in `product-grid.latte` for styling consistency; no JS required for this component.
- Verified via a rendering test (PHP) asserting the produced HTML.

## Requirements (Test Descriptions)
- [x] `it renders an anchor for each page in range`
- [x] `it marks the current page as current`
- [x] `it preserves size and sort query params in every page link`
- [x] `it omits the previous link on the first page`
- [x] `it omits the next link on the last page`
- [x] `it renders a page X of Y label`

## Acceptance Criteria
- Component renders crawlable anchors with correct hrefs (asserted in a render test).
- All requirements have passing tests.

## Implementation Notes
- Created `packages/catalog-storefront/resources/views/components/product-pagination.latte` — pure server-rendered `<nav>` using `mk-stack`, `mk-text` conventions. Iterates `pageLinkUrls` (0-based list from `ProductGridData`) to emit `<a href>` anchors, marks current page with `aria-current="page"`, adds `rel="prev"`/`rel="next"` when `hasPrevious`/`hasNext`, derives prev URL from `pageLinkUrls[$currentPage - 2]`. Latte correctly HTML-escapes `&` to `&amp;` in href attributes.
- Created `packages/catalog-storefront/tests/Unit/Component/ProductPaginationTest.php` — 6 render tests asserting anchors, aria-current marker, escaped query params, prev/next omission, and "Page X of Y" label.
- PHPStan OOM on test directory is a pre-existing infrastructure limitation; `src/` passes level 8 clean.
