# Task 004: Active-filter chips + "Clear all"

**Status**: completed
**Depends on**: 001, 002, 003
**Retry count**: 0

## Description
Style the active-filter summary as a row of removable chips (one per selected value, each a link that
removes just that value) plus a "Clear all" link that drops every attribute filter — so shoppers can see
and undo their selection at a glance.

## Context
- Template: the active-filters block in
  `packages/catalog-attribute-storefront/resources/views/components/facet-sidebar.latte` (renders
  `$activeFilters` — each `ActiveFilter` has `code`, `type`, parallel `labels` + `values` lists). A chip
  per selected value = the value label + a remove "×" affordance, linking to the SAME toggle URL that
  removes that value (reuse `$toggleUrls[$code][$value]`, which for an already-selected value produces the
  deselect URL — VERIFIED in `FacetToggleUrlBuilder::toggle`/`FacetSidebarTest`).
- EDGE CASE (VERIFIED gap): chips iterate `$activeFilter->values`, but `$toggleUrls` is only populated for
  values that appear in the CURRENT `$facets` (see `FacetSidebarComponent::buildToggleUrls`). A selected
  value that is no longer a returned facet value (e.g. it narrowed the result set so the facet query drops
  it) would have NO entry in `$toggleUrls`. The template MUST guard with `{if isset($toggleUrls[$code][$value])}`
  and, when absent, either omit the remove link or fall back to the `clearAllUrl`. Cover this in a test
  (`it still renders a chip whose value has no toggle url without erroring`).
- "Clear all": a link to the category page with NO `filter` params (preserving sort/size). Provide this
  URL to the template. Build it where the toggle URLs are built: add a `clearAllUrl` (nullable string,
  default `null`) to `FacetSidebarData` + populate it in `FacetSidebarComponent::data` reusing the same
  `$baseUrl` + `$currentParams` already assembled in `buildToggleUrls` (which keeps `sort`/`size`, omits
  `page`) but DROP the `filter` key entirely. NOTE: `FacetSidebarData` is a `readonly class` with a
  3-arg constructor and `list<...>` PHPDoc — add the new param as the LAST constructor param with a
  default so existing callers/tests (`new FacetSidebarData()` with no args, used in the null-category and
  empty paths) keep compiling. Set `clearAllUrl` only when at least one filter is active (else `null`);
  render the link only when `$clearAllUrl !== null`.
- WIRING NOTE: `FacetSidebarComponent::data` returns `new FacetSidebarData()` early when `category->id`
  is null — that path must still produce `clearAllUrl: null`. Update `FacetSidebarTest`'s
  `returns empty data when the category id is null` expectation set if you add the field (it currently
  asserts only facets/activeFilters/toggleUrls; a null `clearAllUrl` default keeps it green).
- CSS: add chip styles to `facet-sidebar.css` (`@layer components`, `--mk-*` tokens): `.catalog-facet-active`,
  `.catalog-facet-active__chip`, `…__remove`, `.catalog-facet-active__clear`. Consider `mk-badge` as the
  chip base if it fits. Keep links no-JS.
- Accessibility: each remove link has an accessible name (e.g. `aria-label="Remove {label}"`); "Clear all"
  is a plain link.

## Requirements (Test Descriptions)
- [x] `it renders an active filter chip per selected value`
- [x] `it links each chip remove control to the url that deselects that value`
- [x] `it renders a clear-all link that removes all attribute filters preserving sort`
- [x] `it hides the active filters block when no filters are selected`
- [x] `it gives each remove control an accessible label`
- [x] `it still renders a chip whose value has no toggle url without erroring`

## Acceptance Criteria
- Active filters show as removable chips; each remove deselects only its value; "Clear all" drops all
  `filter` params while keeping sort/size; the block is hidden when nothing is selected.
- `clearAllUrl` is produced by `FacetSidebarComponent`/`FacetSidebarData` (not hard-coded in the template).
- Tests pass; phpcs/phpstan clean.

## Implementation Notes
- Added `clearAllUrl: ?string = null` as the 4th constructor param of `FacetSidebarData` — all existing no-arg callers unaffected.
- Added `FacetSidebarComponent::buildClearAllUrl()` private method: returns null when `$activeFilters` is empty, otherwise builds `/catalog/category/{id}?sort=...&size=...` (omitting `filter` entirely).
- Updated `facet-sidebar.latte`: replaced the old `catalog-facet-sidebar__active-filters` block with a new `catalog-facet-active` container holding `.catalog-facet-active__chips` (one `.catalog-facet-active__chip` per value), each chip's remove link guarded by `{if isset($toggleUrls[$code][$value])}` to handle the missing-toggle-url edge case, and a `.catalog-facet-active__clear` link rendered only when `$clearAllUrl !== null`.
- Added chip + clear-all CSS rules to `facet-sidebar.css` using `@layer components` and `--mk-*` tokens.
- Added a `facetSidebarMakeComponentWithColorAttr()` test helper that pre-seeds the `QueryableAttributeDefinitionRepository` with a facetable 'color' attribute so `FilterParamParser` recognises the 'color' filter param in the clear-all integration test.
