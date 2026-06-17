# Task 008: Facet sidebar UI (component data + Latte)

**Status**: completed
**Depends on**: 007
**Retry count**: 0

## Description
Surface layered navigation on the category page: extend the category product-grid component data with
facet groups + active filters, and render a facet sidebar (values + counts + selected state + toggle
links) in the storefront, with filter selections driven by query params.

## Context
- Pattern: `packages/catalog-storefront/src/Controller/CategoryController.php`,
  `ProductGridComponent` + its `ProductGridData`, and the category Latte template. STUDY how sort
  options are exposed + rendered, and how the controller resolves pagination options.
- WIRING APPROACH (DECIDED — do NOT add a competing `#[Preference]`): `catalog-storefront-scope` ALREADY
  registers `#[Preference(replaces: ProductGridComponent::class)] class ScopedProductGridComponent`.
  Marko allows only ONE preference per target; a second `#[Preference(replaces: ProductGridComponent::class)]`
  from `catalog-attribute-storefront` would be a duplicate-binding conflict (the same class of bug hit in
  Phase 4). Therefore: extend `catalog-storefront`'s OWN `ProductGridComponent` + `ProductGridData`
  IN PLACE to carry optional facet data (e.g. `facets` + `activeFilters`, defaulting to empty so existing
  callers/tests are unaffected), and have the component OPTIONALLY depend on the `LayeredNavigation`
  assembler (task 007) — inject it as a nullable constructor dependency so `catalog-storefront` does NOT
  hard-require `catalog-attribute-storefront`. The facet data is populated only when the assembler is
  present (bound by `catalog-attribute-storefront`). IMPORTANT: `ScopedProductGridComponent` extends
  `ProductGridComponent` and re-constructs `ProductGridData` field-by-field in its `data()` override — if
  you add fields to `ProductGridData`, you MUST also forward them in `ScopedProductGridComponent::data()`
  (and update its `parent::__construct` call if the constructor signature changes), or the scoped path
  will drop the facets. Update `ScopedProductGridComponent` accordingly. Document the chosen wiring.
- Build the `FilterSelection` from the request query params (task 007 parser), pass it to the
  `LayeredNavigation` assembler (task 007); expose `facets` (each: attribute label, `list<{value,
  label, count, selected, toggleUrl}>`) + `activeFilters` to the view.
- `toggleUrl` per facet value: the current category URL with that value added/removed under the
  bracketed `filter` array (`filter[<code>][]=<value>`), preserving other filters + sort + resetting
  page. Build via `http_build_query(['filter' => $selection, 'sort' => …, …])` (the controller already
  uses `http_build_query`, which renders the nested `filter` array as `filter[color][]=red&…`). A small
  URL-builder helper computes the toggled selection (add value if absent, remove if present) then
  rebuilds the query string. Matches the task-007 `filter[...]` parse convention so URLs round-trip.
- Latte: a facet-sidebar partial rendered alongside the product grid — list each facet group with its
  values, counts, checked state, and toggle links. Keep it simple/unstyled (theme-blank conventions).
- Filtered products + facets must reflect the active scope (locale/market) consistently.

## Requirements (Test Descriptions)
- [x] `it exposes facet groups with value counts and selected state in the category component data`
- [x] `it builds a toggle url that adds a value to its attribute filter preserving other params`
- [x] `it builds a toggle url that removes an already-selected value`
- [x] `it renders the facet sidebar with values counts and selected markers`
- [x] `it filters the product grid by the active query-param selection`
- [x] `it forwards facet data through the scoped product grid component override`

## Acceptance Criteria
- The category page renders a working facet sidebar; selecting/deselecting values filters the grid and
  updates counts; selections live in the URL and round-trip.
- Facets + filtered products respect the active scope.

## Implementation Notes

### Container wiring for nullable assembler
The Marko container does NOT skip nullable constructor parameters — it always tries to resolve typed dependencies, even when the PHP type is `?Interface = null`. To ensure `ProductGridComponent` can be resolved when `catalog-attribute-storefront` is not installed:

- `catalog-storefront/module.php` was created to bind `LayeredNavigationAssemblerInterface` → `NullLayeredNavigationAssembler` (a null-object that returns empty facets/activeFilters and delegates product pagination directly to `CategoryAssignmentService`).
- `catalog-attribute-storefront/module.php` overrides that binding with `LayeredNavigationAssembler` when the package is installed.

### Facet sidebar template
Created `packages/catalog-attribute-storefront/resources/views/components/facet-sidebar.latte` which renders facet groups with values, counts, selected markers, and optional toggle URLs (passed as `$toggleUrls[code][value]`).

### Toggle URL test fix
The test for "builds a toggle url that adds a value" used `->or->toContain()` which is not valid Pest 4 API. Fixed to use a single `toContain` assertion with the URL-encoded bracket form (`filter%5Bcolor%5D%5B0%5D=red`), consistent with `http_build_query` output.

### ScopedProductGridComponent container test
Added `LayeredNavigationAssemblerInterface` → `NullLayeredNavigationAssembler` binding to the manual container test so the container can wire `ScopedProductGridComponent` without failing on the assembler dependency.
