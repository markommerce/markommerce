# Plan: Layered Navigation UI (polish)

## Created
2026-06-17

## Status
in_progress

## Objective
Turn the functional-but-unstyled storefront layered navigation into a proper, polished, responsive
category page: a real facet sidebar beside the product grid (using theme-blank's two-column layout),
checkbox-style facet rows, removable active-filter chips with clear-all, and a cohesive visual pass on
the sort control + grid + pagination — all theme-blank-consistent and accessible. No behavior changes.

## Related Issues
none

## Discovery Notes
Builds on the shipped layered-nav wiring (branch `feature/attribute-layered-nav`). Grounded in code:
- **Layout**: `theme-blank` ships `TwoColumnsLeftLayout` (slots `content` + `sidebar-left`, template
  `theme-blank::layout/2columns-left` using the responsive `<mk-sidebar>` lit component). The category
  page (`catalog-storefront/layout/category_show.php`) currently `extends: OneColumnLayout`. The facet
  sidebar is injected by `catalog-attribute-storefront/layout/extensions/category_facets.php`, currently
  Prepending into the `content` slot.
- **CSS pipeline**: each package ships `resources/css/components/*.css` written as `@layer components { … }`
  using `--mk-*` design tokens (open-props-backed, from `theme-blank`); the package's
  `resources/js/index.ts` `import`s its CSS; `package.json` declares `markommerce.extension` (+ priority)
  and `exports`. `frontend` defines the cascade-layer order (`layers.css`). **`catalog-attribute-storefront`
  has NO `package.json`/`resources/js`/`resources/css` yet** — it needs the same asset setup.
- **Sidebar component** (`FacetSidebarComponent` → `facet-sidebar.latte`) already exposes facets
  (code, values w/ label+count+selected), `activeFilters`, and `toggleUrls[code][value]`. The grid's
  `ProductGridData` carries `appliedFilters`. Templates use `mk-*` components (mk-stack/inline/text/badge).

Resolved decisions (clarification):
- **Two-column always**: category page switches to `TwoColumnsLeftLayout`; facets render in `sidebar-left`.
  Empty/near-empty left rail when a category has no facets is acceptable (handle gracefully in CSS).
- **No-JS toggles**: keep the existing anchor-link toggles, styled as checkbox-like rows (no new JS, no
  behavior change). Progressive enhancement only.
- **CSS ownership**: facet/sidebar CSS lives in `catalog-attribute-storefront` (its own package.json +
  frontend extension), keeping `catalog-storefront` attribute-agnostic.
- **Restyle scope**: also polish the product grid, sort control, and pagination for a cohesive page.

## Scope

### In Scope
- Frontend asset pipeline for `catalog-attribute-storefront` (package.json + `resources/js/index.ts` +
  `resources/css/components/facet-sidebar.css`), wired into the bundle like sibling packages.
- Two-column category layout: `category_show.php` extends `TwoColumnsLeftLayout`; the facet extension
  targets the `sidebar-left` slot; product grid stays in `content`.
- Restyled facet sidebar: facet group headers, checkbox-style value rows (glyph + label + count +
  selected state), accessible markup (fieldset/legend or aria), graceful empty state.
- Active-filter chips: removable chips + a "Clear all" affordance.
- Visual polish on the sort control, product-grid spacing, and pagination (theme-blank-consistent).
- Template + CSS-presence + layout-definition tests; playground visual verification.
- README/docs updates.

### Out of Scope
- Any behavior change to filtering/faceting/sorting/pagination (logic is done + tested).
- New JS interactivity (checkbox auto-submit, AJAX facets, drawers requiring JS) — no-JS only.
- New facet types / range-slider widgets / multi-attribute UX beyond what already ships.
- Theme-blank component changes beyond consuming existing `mk-*` components + tokens.

## Success Criteria
- [ ] The category page renders the facet sidebar in a real left column beside the grid (two-column,
      responsive via `<mk-sidebar>`), not stacked above it.
- [ ] Facet values render as styled checkbox-like rows with counts + a clear selected state; toggling
      still works with JS disabled (anchor links unchanged).
- [ ] Active filters render as removable chips with a working "Clear all" link.
- [ ] Sort control, product grid, and pagination get a cohesive visual pass using `--mk-*` tokens.
- [ ] `catalog-attribute-storefront` ships its CSS via its own frontend-asset setup; `catalog-storefront`
      gains no attribute dependency.
- [ ] All tests passing (template/CSS/layout); phpcs / phpstan level 8 clean; verified in the playground.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Frontend asset pipeline for `catalog-attribute-storefront` (package.json + js/css entry) | - | completed |
| 002 | Two-column category layout (extends TwoColumnsLeftLayout; extension → `sidebar-left`) | - | completed |
| 003 | Restyle facet sidebar template + CSS (checkbox-style rows, group headers, a11y, empty state) | 001, 002 | completed |
| 004 | Active-filter chips + "Clear all" affordance | 001, 002, 003 | completed |
| 005 | Visual polish: sort control + product grid + pagination | 002 | completed |
| 006 | Playground verification + category-page render Feature test | 003, 004, 005 | completed |
| 007 | READMEs + docs (UI + new frontend assets + two-column layout) | 001-006 | completed |

## Architecture Notes
- **Decoupling preserved**: the two-column switch lives in `catalog-storefront` (`category_show.php`);
  the facet placement + all facet CSS live in `catalog-attribute-storefront`. `catalog-storefront` never
  references attribute code. `<mk-sidebar>` handles responsive stacking — no custom media-query JS.
- **CSS**: write `@layer components { .catalog-facet-… { … } }` using `--mk-*` tokens with sensible
  fallbacks (mirror `product-card.css`). Import CSS from the package's `resources/js/index.ts`; declare
  `markommerce.extension` + `exports` in `package.json` (mirror `catalog-storefront/package.json`).
- **ASSET LOAD PATH (VERIFIED — two mechanisms, not one):** (1) `build/vite-plugin-markommerce.ts` scans
  `packages/*` for `markommerce.extension` and auto-generates `frontend-demo/.../extensions.ts` (JS harness
  path); a correct `package.json` is auto-picked. (2) The CSS that actually loads on a STOREFRONT page comes
  from an EXPLICIT `{vite('packages/<pkg>/resources/js/index.ts')}` in
  `packages/theme-blank/resources/views/layout/base.latte` PLUS a matching `rollupOptions.input` entry in
  `vite.config.ts`. catalog-storefront is already wired this way (`catalogStorefront` input + a base.latte
  line). The new package needs BOTH added (task 001) — `package.json` alone is NOT enough for the page CSS.
- **No-JS toggles**: keep `<a href="{toggleUrl}">`; style as checkbox-like rows via CSS (e.g. a
  `::before` box + `aria-pressed`/selected class). Selected state already provided by the component.
- **Accessibility**: group facets with `<fieldset><legend>` or a labelled region; mark selected values
  with `aria-current`/`aria-pressed`; "Clear all" + chip removes are plain links.

## Risks & Mitigations
- **Empty left rail** when a category has no facets: hide/condense the sidebar region via CSS (or render
  a muted "No filters" note); cover an empty-facets render case in tests.
- **Asset bundling not picked up in playground** (HIGH — the original "mirror package.json" assumption was
  insufficient): the storefront CSS-load path requires a `vite.config.ts` `rollupOptions.input` entry AND a
  `{vite(...)}` line in `theme-blank/base.latte`, NOT just `package.json`. Task 001 now adds both; task 006
  verifies the manifest entry + rendered `<link>`.
- **Layout switch breaks existing storefront tests** (re-checked against the code): CategoryLayoutTest's
  assertions (`<mk-stack`, category name, `No products found`, no fake-view string) come from the grid in
  the `content` slot and SHOULD survive the wrap; RelocationTest and CategoryFacetsExtensionTest do NOT
  assert `OneColumnLayout`/`content` slotPath, so they should NOT break. Task 002 re-runs them and adjusts
  only real failures rather than rewriting pre-emptively.
- **`DanglingAnchorException` if edits split**: the `extends` switch (`category_show.php`) and the `slotPath`
  change (`category_facets.php`) must ship together; targeting `sidebar-left` before the layout extends
  `TwoColumnsLeftLayout` throws at compile time. Both are in task 002.
- **Cross-package template coupling**: the facet sidebar template + CSS stay in
  `catalog-attribute-storefront`; only the slot *name* (`sidebar-left`) is shared (a theme-blank contract).
