# Task 011: READMEs + docs

**Status**: completed
**Depends on**: 001, 002, 003, 004, 005, 006, 007, 008, 009, 010
**Retry count**: 0

## Description
Document the layered-navigation feature: the new `catalog-attribute-storefront` README, the `catalog`
filter extension point, the `catalog-attribute-index` facet query, and the Phase-4 full-materialization
amendment. Update docs pages.

## Context
- Standard: `.claude/package-standard.md` + Package README Standards in `.claude/code-standards.md`.
  Mirror sibling READMEs (`catalog-storefront`, `catalog-price-index`).
- `catalog-attribute-storefront/README.md`: purpose (layered navigation); the query-param convention
  (bracketed `filter` array: `?filter[color][]=red&filter[color][]=blue&filter[size][]=L`);
  `AttributeProductListFilter` + the facet sidebar; that it builds on the
  `catalog` filter registry + `catalog-attribute-index` facet query; disjunctive faceting; scope-aware.
- Docs (`docs/src/content/docs/packages/`): new `catalog-attribute-storefront.md`; update
  `catalog-attribute-index.md` (facet query + the full-materialization change — note the index now
  materializes a row per served signature) and `catalog.md`/`catalog-storefront.md` (the generic
  `ProductListFilterInterface`/registry + category-page facets). Cross-link.
- Note PHP 8.5 / no-final / strict-types. Reflect only shipped behavior.

## Requirements (Test Descriptions)
- [x] `it documents the catalog-attribute-storefront layered navigation and query-param convention in its README`
- [x] `it documents the catalog product-list filter registry extension point`

> Note: documentation-completeness checks — verify the README/docs exist and contain the required sections.

## Acceptance Criteria
- READMEs + docs follow the standard; content matches shipped behavior (incl. the Phase-4 full-materialization change).

## Implementation Notes
- Created `packages/catalog-attribute-storefront/tests/ReadmeTest.php` with two completeness tests.
- Replaced placeholder `packages/catalog-attribute-storefront/README.md` with full documentation covering: query-param convention, `AttributeProductListFilter`, `LayeredNavigationAssembler`, `FilterParamParser`, `FacetToggleUrlBuilder`, disjunctive faceting, scope-awareness, facet sidebar template, module bindings, API reference, and related packages.
- Updated `packages/catalog/README.md` to add `ProductListFilterInterface`/`ProductListFilterRegistry`/`FilterSelection` extension point section with usage example and cross-link to `catalog-attribute-storefront`.
- Created `docs/src/content/docs/packages/catalog-attribute-storefront.md` (new docs page).
- Updated `docs/src/content/docs/packages/catalog-attribute-index.md`: added full-materialization note, `AttributeFacetQuery` and `AttributeExistsClause` usage sections and API reference, cross-link to `catalog-attribute-storefront`.
- Updated `docs/src/content/docs/packages/catalog.md`: added "Product list filter extension point" usage section, cross-link in Related Packages.
- Updated `docs/src/content/docs/packages/catalog-storefront.md`: added `$facets`/`$activeFilters` fields to `ProductGridData` table, `LayeredNavigationAssemblerInterface` API reference section, cross-link to `catalog-attribute-storefront`.
