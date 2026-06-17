# Task 007: Layered-nav assembler + category controller filter-param parsing

**Status**: completed
**Depends on**: 004, 005, 006
**Retry count**: 0

## Description
Assemble the layered-navigation result (filtered product page + facets + active filters) for a category
+ scope + filter selection, and parse filter selections from the category request's query params.

## Context
- Place the assembler in `packages/catalog-attribute-storefront/src/` (e.g. `LayeredNavigation`):
  `forCategory(int $categoryId, ResolvedPaginationOptions $options, FilterSelection $selection): LayeredNavigationResult`
  where the result holds the products `Page` (from `CategoryAssignmentService::paginatedProductsInCategory`
  with the selection — task 004) + `list<Facet>` (from `AttributeFacetQuery` — task 005). Inject
  `CategoryAssignmentService` + `AttributeFacetQuery`. Enrich facet/option display labels via the
  `attribute-scope` package's `Markommerce\AttributeScope\ScopedOptionLabelResolver` (NOT
  `catalog-attribute-scope` — verify the namespace) where the attribute is a `select`/`multiselect` type;
  fall back to the raw value otherwise. NOTE its signature is
  `resolve(AttributeOption $option, AttributeOptionScopedLabels $labels, ScopeContext $context): string`,
  so the assembler must first look up the `AttributeOption` for each facet value (by attribute + value)
  and its `AttributeOptionScopedLabels` companion — keep this lookup behind a small helper, and if the
  option/labels aren't found, fall back to the raw facet value. Add `markommerce/attribute-scope` +
  `markommerce/attribute` to the package's composer deps (task 002) if not already present.
- Filter-param parsing: a small helper that turns request query params into a `FilterSelection`.
  **Convention (DECIDED): a bracketed `filter[...]` array** — `?filter[color][]=red&filter[color][]=blue&filter[size][]=L`.
  CONFIRMED feasible: `Marko\Routing\Http\Request::fromGlobals` populates `query` from `$_GET`, which
  PHP natively expands into nested arrays, so `$request->query('filter')` returns
  `['color' => ['red','blue'], 'size' => ['L']]` directly. This namespaces all attribute filters under
  `filter` — NO collision with reserved params (`page`/`size`/`sort`/`view`/`position`). The parser:
  read `$request->query('filter')` (default `[]`); normalize each entry to a `list<string>` (a scalar
  `filter[color]=red` → `['red']`); keep ONLY keys matching known facetable attribute codes (ignore
  unknown); build the `FilterSelection`. Repeated `[]` = OR within an attribute.
- The category controller integration itself (reading params, calling the assembler, passing data to the
  view) is wired in task 008 / via the storefront; this task delivers the assembler + the param→selection
  parser as reusable units with unit tests (fakes for the listing + facet services).

## Requirements (Test Descriptions)
- [x] `it assembles the filtered product page and facets for a category and selection`
- [x] `it parses the bracketed filter array query param into a multi-value filter selection`
- [x] `it normalizes a scalar filter value into a single-element list`
- [x] `it ignores filter keys that are not known facetable attribute codes`
- [x] `it resolves select-option display labels for the active scope`
- [x] `it returns the active filters alongside the facets`

## Acceptance Criteria
- One call returns the filtered page + disjunctive facets + active filters for a category + scope + selection.
- Query-param parsing yields a `FilterSelection` limited to known facetable attribute codes.

## Implementation Notes
(Left blank - filled in by programmer during implementation)
