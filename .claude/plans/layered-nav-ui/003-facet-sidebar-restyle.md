# Task 003: Restyle the facet sidebar (checkbox-style rows + group headers + a11y + CSS)

**Status**: completed
**Depends on**: 001, 002
**Retry count**: 0

## Description
Restyle the facet sidebar so each facet group has a clear header and its values render as checkbox-like
rows (a checkbox glyph + label + count, with an obvious selected state), accessible and usable without
JS. Author the real CSS in the package stylesheet created in task 001.

## Context
- Template: `packages/catalog-attribute-storefront/resources/views/components/facet-sidebar.latte`.
  It receives `$facets` (each: `code`, `type`, `values[]` of `{value,label,count,selected}`),
  `$activeFilters`, and `$toggleUrls[code][value]`. Toggles MUST stay no-JS anchor links
  (`<a href="{$toggleUrls[...]}">`) — only the PRESENTATION changes.
- Render each facet group with a heading (the attribute `code`/label) and a list of value rows. Each row:
  a checkbox-style indicator (CSS `::before` box, checked when `$facetValue->selected`), the value
  `label`, and the `count`. Wrap the whole row in the toggle `<a>` so the entire row is clickable.
  Use `aria-pressed="true"`/`aria-current` (or a visually-hidden state) on selected rows for a11y, and
  group with `<fieldset><legend>` or a labelled `role="group"`. Keep `mk-*` components where they help
  (mk-stack/mk-text) but the row styling is CSS-class-driven.
- CSS: author rules in `packages/catalog-attribute-storefront/resources/css/components/facet-sidebar.css`
  inside `@layer components`, using `--mk-*` tokens with fallbacks (MIRROR `catalog-storefront/resources/css/components/product-card.css`):
  classes like `.catalog-facet-sidebar`, `.catalog-facet-sidebar__group`, `…__group-title`,
  `…__value`, `…__value--selected`, `…__checkbox`, `…__count`. The checkbox glyph is a CSS box +
  checkmark on `--selected`. Style the sidebar container for the `sidebar-left` column.
- EMPTY STATE: when `$facets` is empty (a category with no facetable values), render nothing heavy — an
  empty container or a muted note — and ensure the CSS doesn't leave an awkward empty rail.
- Keep the existing class hooks the integration tests rely on intact where practical (or update those
  tests); the existing facet-sidebar Latte already uses `catalog-facet-sidebar`,
  `catalog-facet-sidebar__group` (with `data-facet-code`), `catalog-facet-sidebar__value`,
  `…__value--selected`, `…__value-label`, `…__value-count`, `…__value-selected`, `…__toggle`,
  `…__active-filters`, `…__active-filter` classes.
- EXISTING RENDER TEST TO UPDATE: `catalog-attribute-storefront/tests/Unit/LayeredNavigation/FacetSidebarTest.php`
  has a test `renders the facet sidebar with values counts and selected markers` that renders the Latte via
  the real engine and asserts the output contains `color`, `Red`, `3`, `Blue`, `5`, `selected`. The restyle
  must keep these substrings present (label + count + a `selected` marker), OR update that test in lockstep.
  Note it renders WITHOUT passing `toggleUrls`, so the template's `{if isset($toggleUrls[...])}` guard must
  stay (a missing `toggleUrls` must not error) — keep wrapping the toggle link in an `isset` guard.
- EMPTY STATE (VERIFIED): the template already defends with `{var $facetGroups = $facets ?? []}`; the
  empty-facets render must not emit group/active-filter chrome. Add a render test for the empty case.

## Requirements (Test Descriptions)
- [x] `it renders each facet group with a titled group container`
- [x] `it renders facet values as checkbox-style rows with label and count`
- [x] `it marks selected facet values with a selected state and aria attribute`
- [x] `it keeps facet toggles as no-js anchor links`
- [x] `it renders an empty sidebar gracefully when there are no facets`
- [x] `it defines facet sidebar styles in the components layer using mk design tokens`

## Acceptance Criteria
- Facet groups have headers; values are checkbox-style rows with counts + clear selected state; toggles
  remain anchor links (no JS). Empty facets render gracefully.
- CSS lives in `catalog-attribute-storefront`'s `facet-sidebar.css` under `@layer components` using `--mk-*` tokens.
- Render/CSS-presence tests pass; phpcs/phpstan clean.

## Implementation Notes
- Latte template (`facet-sidebar.latte`) rewritten: `<div>` container with BEM classes, `role="group"` + `aria-labelledby` on each group `<div>`, `<p class="catalog-facet-sidebar__group-title">` heading, `<ul>/<li>` value list, `<span class="catalog-facet-sidebar__checkbox">` glyph inside toggle link/span, `aria-pressed="true"` on selected toggle `<a>`, `<span class="catalog-facet-sidebar__count">` for the count. `{if isset($toggleUrls[...])}` guard preserved so rendering without `toggleUrls` does not error.
- CSS (`facet-sidebar.css`) authored under `@layer components` using `--mk-*` tokens with fallbacks. Covers: `.catalog-facet-sidebar` (flex column, hides when empty via `:empty`), `__group`, `__group-title`, `__values` (unstyled list), `__value`, `__toggle`, `__checkbox` (box with `::after` checkmark on `--selected`), `__value-label`, `__count`, `__value--selected` modifier.
- Added a shared `facetSidebarMakeLatteEngine()` / `facetSidebarRender()` helper in the test file to DRY up the 5 new render tests.
- Fixed `dirname(__DIR__, 4)` → `dirname(__DIR__, 3)` in the CSS-presence test (the test file is 3 levels deep from the package root, not 4).
- All 12 tests in `FacetSidebarTest.php` pass; full suite 2345 passed; phpcs + phpstan clean.
