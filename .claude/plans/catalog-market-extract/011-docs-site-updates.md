# Task 011: Docs site updates

**Status**: completed
**Depends on**: 010
**Retry count**: 0

## Description
Add three new package pages to the docs site (`docs/src/content/docs/packages/`), update `catalog.md` to reflect the post-P4 state, and add a docs test that asserts the new pages exist and the catalog page no longer mentions the removed APIs.

## Context
- Existing pages to mirror:
  - `docs/src/content/docs/packages/locale.md` — for `market.md`
  - `docs/src/content/docs/packages/catalog-locale.md` — for `catalog-market.md`
  - `docs/src/content/docs/packages/catalog-scope.md` — for `catalog-market-category-trees.md`
- Page outlines:
  - `market.md` — axis declaration, axis config shape, future-axis-helpers note.
  - `catalog-market.md` — placeholder status, planned fields, cross-link to FEATURES.md, install/uninstall guidance.
  - `catalog-market-category-trees.md` — install, quick example, the three services (`CategoryTreeMarketResolver`, `CategoryTreeMarketAssignmentService`, plus the `CategoryTreeServiceDeletePlugin`), Tier 3 wiring diagram (text-only OK).
  - `catalog.md` — drop any market-axis section; add a one-line cross-link to `catalog-market-category-trees`.
- Docs test pattern: `tests/Unit/Docs/CatalogScopeDecouplePagesTest.php` and `CatalogStorefrontExtractPagesTest.php`. Add `tests/Unit/Docs/CatalogMarketExtractPagesTest.php` that:
  - Asserts the three new files exist.
  - Asserts `catalog.md` no longer contains `resolveTreeForMarket`, `assignTreeToMarket`, or `unassignMarket`.
  - Asserts `catalog-market-category-trees.md` mentions `CategoryTreeMarketResolver`, `CategoryTreeMarketAssignmentService`, and `CategoryTreeServiceDeletePlugin`.
  - Asserts `catalog-market.md` documents its placeholder status.

## Requirements (Test Descriptions)
- [x] `it ships a docs page at docs/src/content/docs/packages/market.md with axis declaration content`
- [x] `it ships a docs page at docs/src/content/docs/packages/catalog-market.md that documents placeholder status`
- [x] `it ships a docs page at docs/src/content/docs/packages/catalog-market-category-trees.md that documents the resolver, assignment service, and delete plugin`
- [x] `the updated catalog.md no longer mentions resolveTreeForMarket, assignTreeToMarket, or unassignMarket`
- [x] `the updated catalog.md cross-links to the catalog-market-category-trees page`
- [x] `tests/Unit/Docs/CatalogMarketExtractPagesTest.php exists and all its assertions pass`

## Acceptance Criteria
- Three new docs pages exist with the required content.
- `catalog.md` is updated and no longer mentions removed APIs.
- New docs test passes.
- The docs site builds without errors (`npm run build` in `docs/` or whichever build command the project uses).

## Implementation Notes
- Place the docs test at the monorepo root: `tests/Unit/Docs/CatalogMarketExtractPagesTest.php` (matches `CatalogScopeDecouplePagesTest.php` and `CatalogStorefrontExtractPagesTest.php` locations from the prior phases). Do NOT place it under `packages/catalog/tests/` — the docs tests intentionally live at the root because they assert presence of files outside any single package.
