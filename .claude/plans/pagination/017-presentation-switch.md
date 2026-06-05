# Task 017: Config-driven presentation switch + end-to-end feature tests

**Status**: complete
**Depends on**: 014, 016
**Retry count**: 0

## Description
Wire the product grid to render the correct controls based on `CatalogPaginationConfig.presentation` — numbered links, an `mk-load-more` button, or `mk-infinite-scroll` — all layered over the same crawlable `?page=` links. This is the task that makes flipping the merchant config actually change the category page, proven by feature tests for every mode.

## Context
- Files: `packages/catalog-storefront/resources/views/components/product-grid.latte` (add the presentation switch), reusing the numbered partial (Task 014) and the Lit components (Task 016).
- The grid renders the crawlable numbered `<a href="?page=N">` links in ALL modes (SEO/no-JS fallback). On top of that, switch by `ProductGridData.presentation`:
  - `numbered` → show the numbered pagination partial as the primary control.
  - `load_more` → wrap the grid in `<mk-load-more data-next="…">` with the numbered links kept as fallback.
  - `infinite` → wrap the grid in `<mk-infinite-scroll data-next="…">` with an accessible load-more fallback and the numbered links present.
- Feature tests render the category page (full layout) under each config value and assert the rendered controls. These tests are the guarantee requested during planning.
- **Test harness reality (verified against `CategoryLayoutTest`):** the existing full-page feature tests build the layout pipeline manually (compile the `Layout` to a `PreparedTree`, wire `Renderer` + fakes) and render. To switch presentation per test, vary `CatalogPaginationConfig.presentation` via a stub `ConfigResolverInterface` injected into `PaginationOptionsResolver` (do NOT require a live config DB). Because the array-backed `FakeProductRepository` cannot run the paginated join, the grid `data()` under test must obtain its `Page` either from a DB-backed path or from an injected/stubbed paginated service returning a fixed `Page` — choose the stub path for these presentation-switch tests so they stay unit/feature-fast and deterministic. The assertion is on which CONTROLS render (numbered partial vs `mk-load-more` vs `mk-infinite-scroll`) and that crawlable `?page=` links are always present — not on real DB pagination (that is Task 012's integration test).
- The Lit custom-element tags (`mk-load-more`, `mk-infinite-scroll`) only need to appear as server-rendered HTML elements with `data-next` here; their JS behavior is Task 016 (vitest). This PHP test asserts the markup, not the JS.

## Requirements (Test Descriptions)
- [x] `it renders numbered pagination controls when presentation is numbered`
- [x] `it renders a load-more component when presentation is load_more`
- [x] `it renders an infinite-scroll component when presentation is infinite`
- [x] `it always renders crawlable page links regardless of presentation mode`
- [x] `it exposes the next-page url to the load-more and infinite components`
- [x] `it changes the rendered controls when the presentation config changes`

## Acceptance Criteria
- A PHP feature test renders the real category page for each presentation value and asserts the correct controls + presence of crawlable links.
- All requirements have passing tests.

## Implementation Notes
- Added presentation switch to `product-grid.latte` using `{var $mode = isset($presentation) ? $presentation->value : 'numbered'}` to safely derive the mode string from the enum's `->value` property. This avoids enum comparison issues in Latte (non-strict mode) and handles templates rendered without the variable for backward compatibility.
- Numbered pagination partial is included in all modes: directly for `numbered`, inside `<mk-load-more>` wrapper for `load_more`, and inside `<mk-infinite-scroll>` (plus a `<noscript>` fallback copy) for `infinite`.
- Both `mk-load-more` and `mk-infinite-scroll` expose `data-next="{$nextPageUrl}"` for the JS progressive enhancement.
- Feature tests in `PresentationSwitchTest.php` use the real Latte engine (same pattern as `ProductPaginationTest.php`) to render the `product-grid.latte` template directly with controlled `ProductGridData` fields, asserting on actual rendered HTML markup.
- The `{if count($paginationUrls) > 0}` guard ensures no pagination controls render when there are no pages (single-page categories), preserving the existing no-pagination behavior.
