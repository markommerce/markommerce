# Task 002: Templates — data-prev/data-canonical + Load-previous & Load-more buttons

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Surface the backward + canonical URLs in the rendered markup: the fragment wrapper gains `data-prev` + `data-canonical`, the `mk-*` elements receive them, a "Load previous" button is rendered above the grid when a previous page exists, and `mk-load-more` gets a real "Load more" button (it has none today).

## Context
- Files: `packages/catalog-storefront/resources/views/components/product-grid-fragment.latte`, `packages/catalog-storefront/resources/views/components/product-grid.latte`. Data flow is automatic: `ProductGridData`'s public props become template variables (that is how `$nextPageUrl`, `$presentation`, etc. already reach both templates). Once Task 001 adds `previousPageUrl`/`canonicalPageUrl` as public props they are available in BOTH templates with no manual threading — do NOT edit `category_page_fragment.php`.
- **MUST guard new variables defensively.** The existing `PresentationSwitchTest::presentationRenderGrid()` helper renders `product-grid.latte` with an EXPLICIT data array that does NOT include `previousPageUrl`/`canonicalPageUrl`. The current template already guards optional vars (`$pageLinkUrls ?? []`, `$hasPrevious ?? false`, `$nextPageUrl ?? null`). Reference the new vars the same way: `{$previousPageUrl ?? null}`, `{$canonicalPageUrl ?? null}` / `{if ($previousPageUrl ?? null) !== null}`. An unguarded `{$previousPageUrl}` will throw an undefined-variable error and break the existing PresentationSwitch tests. The `product-grid-fragment.latte` test path passes data via the FakeView which only forwards scalars — same guard discipline applies.
- **Attribute contract (must match Tasks 004/005):** the fragment wrapper `.catalog-product-grid-fragment` carries `data-next` (existing), and adds `data-prev="{$previousPageUrl}"` (omit when null) + `data-canonical="{$canonicalPageUrl}"`. The `mk-load-more` / `mk-infinite-scroll` elements in `product-grid.latte` carry `data-next`, `data-prev` (omit when null), `data-canonical`, and `data-grid=".catalog-product-grid"`.
- `product-grid.latte`:
  - `load_more` + `infinite` branches: add `data-prev`/`data-canonical` to the element. When `previousPageUrl` is set, render a clickable **"Load previous"** `<button type="button" class="catalog-pagination__load-previous" data-role="load-previous">Load previous</button>`. **PINNED STRUCTURE (option b — do not re-decide):** the `<mk-grid class="catalog-product-grid">` is a SIBLING above the `mk-*` pagination element. Render the "Load previous" button as its own element **immediately BEFORE `<mk-grid>`** (same parent — the `mk-stack`), inside an `{if ($previousPageUrl ?? null) !== null}` guard, so it sits visually above the grid with no CSS repositioning. It is therefore NOT a child of the `mk-*` element — the JS (Tasks 004/005, which live below the grid) locates it via the grid's parent: `grid.parentElement?.querySelector('[data-role="load-previous"]')`. Keep `data-role="load-previous"` + the class for styling (Task 006). Because the button is outside the `mk-*` element, its `data-prev`/`data-canonical` come from the `mk-*` element (the component reads them from itself), not from the button. (Forward "Load more"/sentinel stay inside the `mk-*` element below the grid, which is correct for forward.)
  - `load_more` branch: also render a **"Load more"** `<button type="button" data-role="load-more">Load more</button>` (the component currently finds none). Keep numbered pagination as the `:defined`-hidden crawlable fallback.
  - Use `data-role` attributes (`load-more` / `load-previous`) so the JS can find each button unambiguously (mk-infinite-scroll injects its own forward button, but mk-load-more relies on these).
- Escaping: URLs via `|noescape` as the existing `data-next` does (they are app-built, not user input).

## Requirements (Test Descriptions)
- [x] `it emits data-prev on the fragment wrapper when a previous page exists`
- [x] `it omits data-prev on the fragment wrapper for the first page`
- [x] `it emits data-canonical on the fragment wrapper`
- [x] `it renders a load-previous button above the grid when previousPageUrl is set`
- [x] `it does not render a load-previous button on the first page`
- [x] `it renders a load-more button in load_more mode`
- [x] `it passes data-prev and data-canonical to the mk element`

## Acceptance Criteria
- Fragment + grid templates emit the pinned attributes; Load-previous renders only when a previous page exists; load_more has a button.
- New template vars are referenced with `?? null` guards so renders that omit them (the existing `PresentationSwitchTest` helper) do not error.
- Existing presentation-switch / fragment feature tests still pass UNCHANGED (do not modify the `presentationRenderGrid` helper just to satisfy new attributes — the guards make it pass as-is).
- All requirements have passing tests (PHP render/feature tests).

## Implementation Notes
- `product-grid-fragment.latte`: added `data-prev` (omitted when null) + `data-canonical` (emitted unconditionally when not null) to the fragment wrapper, using `?? null` guards.
- `product-grid.latte`: moved `$mode` and `$paginationUrls` variable declarations before the products block so the load-previous guard can be evaluated early. Load-previous button renders immediately before the `{if count($products) > 0}` block (same parent as `mk-grid`). Added `data-prev`/`data-canonical` to `mk-load-more` and `mk-infinite-scroll`. Added `<button data-role="load-more">` inside `mk-load-more`.
- All new template vars guarded with `?? null` so `PresentationSwitchTest::presentationRenderGrid()` (which omits them) still passes unchanged.
- New test file: `packages/catalog-storefront/tests/Feature/ProductGridTemplateTest.php`.
