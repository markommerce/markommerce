# Task 002: Two-column category layout (facet sidebar beside the grid)

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Make the category page a real two-column layout: extend theme-blank's `TwoColumnsLeftLayout` (which
renders a responsive `<mk-sidebar>` with `sidebar-left` + `content` slots) and move the facet sidebar
placement from the `content` slot into `sidebar-left`, leaving the product grid in `content`.

## Context
- `packages/catalog-storefront/layout/category_show.php`: change `extends: OneColumnLayout::class` →
  `extends: TwoColumnsLeftLayout::class` (`Markommerce\ThemeBlank\Layout\TwoColumnsLeftLayout`). Keep the
  grid placement in the `content` slot. STUDY `TwoColumnsLeftLayout::define()` (slots `content` +
  `sidebar-left`, template `theme-blank::layout/2columns-left`).
- `packages/catalog-attribute-storefront/layout/extensions/category_facets.php`: change the facet-sidebar
  `Prepend` to target `slotPath: 'sidebar-left'` (instead of `'content'`). Keep the `MergeProps` that adds
  the `filter` array source to the `catalog.product_grid` placement unchanged.
- The category PAGE FRAGMENT layout (`category_page_fragment.php`, if present) renders only the grid — do
  NOT add a sidebar there (fragments are the AJAX/no-chrome grid). Leave it one-column.
- WIRING NOTE: only `catalog-storefront` switches the layout; the facet placement stays in
  `catalog-attribute-storefront`. When that package is absent, `sidebar-left` is simply empty (acceptable;
  CSS in task 003 handles the empty state). `catalog-storefront` gains no attribute dependency.
- Existing-test reality (VERIFIED against the code — do NOT blindly rewrite, RE-RUN and adjust only what
  actually fails):
  - `catalog-storefront/tests/Feature/CategoryLayoutTest.php`: its assertions are
    `toContain('<mk-stack')`, `toContain('Real Layout Category')`, `toContain('No products found')`, and
    `not->toContain('catalog-storefront::components/product-grid')`. All of these come from the grid
    template, which stays in the `content` slot, so they SHOULD survive the switch — but the
    `2columns-left.latte` wraps everything in `<mk-container><mk-sidebar><aside>…</aside>{slot content}</mk-sidebar>`.
    Run this test after the switch; only update if the wrapper markup breaks an assertion. The
    `compiles the category_show layout without error` test compiles with ONLY the catalog-storefront module
    registered — `TwoColumnsLeftLayout::define()` is resolved by PHP autoload (not module discovery), and
    catalog-storefront already depends on theme-blank, so it resolves with no extra wiring. Verify it stays green.
  - `tests/Unit/RelocationTest.php`: VERIFIED — it does NOT reference `OneColumnLayout`/`1column`; it only
    asserts the layout file exists, uses the `Markommerce\CatalogStorefront\` namespace, and the
    `catalog-storefront::` template prefix. The switch keeps all of those true, so it should NOT need changes.
  - `catalog-attribute-storefront/tests/Unit/Layout/CategoryFacetsExtensionTest.php`: VERIFIED — it only
    asserts the extension handle + non-empty operations, NOT the `slotPath`, so changing `content` →
    `sidebar-left` does NOT break it. (Optionally tighten it to assert the Prepend now targets `sidebar-left`.)
  - There is NO standalone layout-definition test asserting `OneColumnLayout` for the category page.
- SLOT-TARGETING MECHANISM (VERIFIED): the layout compiler's `ResolutionPhase` resolves the `extends` chain
  FIRST (`resolveExtendsChain` merges `TwoColumnsLeftLayout`'s `sidebar-left` + `content` slots into the
  child), THEN applies extensions. `Prepend` throws `DanglingAnchorException` if the target slot is absent
  from the resolved slot map. So once `category_show.php` extends `TwoColumnsLeftLayout`, `sidebar-left`
  exists in the resolved tree and the extension's `Prepend(slotPath: 'sidebar-left', …)` resolves cleanly.
  Both edits (the `extends` switch in `category_show.php` AND the `slotPath` change in `category_facets.php`)
  MUST land together — shipping only the `slotPath` change without the layout switch would throw
  `DanglingAnchorException` at compile time.

## Requirements (Test Descriptions)
- [x] `it extends the two-columns-left theme layout for the category page`
- [x] `it keeps the product grid placement in the content slot`
- [x] `it places the facet sidebar into the sidebar-left slot`
- [x] `it still feeds the filter query param into the product grid placement`
- [x] `it leaves the category page fragment layout single-column`

## Acceptance Criteria
- Category page extends `TwoColumnsLeftLayout`; grid in `content`, facet sidebar in `sidebar-left`.
- The page-fragment layout is unchanged (no sidebar).
- `catalog-storefront` has no dependency on `catalog-attribute-storefront`; existing layout tests updated + green.

## Implementation Notes
- Changed `category_show.php` to extend `TwoColumnsLeftLayout::class` instead of `OneColumnLayout::class`.
- Changed `category_facets.php` `Prepend` operation's `slotPath` from `'content'` to `'sidebar-left'`.
- Added `CategoryShowLayoutDefinitionTest.php` in `catalog-storefront/tests/Unit/` covering requirements 1, 2, and 5.
- Extended `CategoryFacetsExtensionTest.php` with two new tests covering requirements 3 and 4.
- The 6 pre-existing ViteManifestException failures in `theme-blank` and `layout-demo` packages were present before these changes (Task 001 concern) and are unrelated.
