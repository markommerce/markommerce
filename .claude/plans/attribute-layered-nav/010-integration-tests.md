# Task 010: Integration tests (filter narrowing, disjunctive facets, scope)

**Status**: completed
**Depends on**: 004, 005, 006, 007, 009
**Retry count**: 0

## Description
DB-backed integration tests proving layered navigation end-to-end against real Postgres: filtering
narrows the category listing, facet counts are correct and disjunctive, and both respect the active
scope — over the full-materialized index.

## Context
- Pattern: the Phase-4 `packages/catalog-attribute-index/tests/Feature/AttributeIndexIntegrationTest.php`
  + `catalog-storefront` Feature tests + the harness (`IntegrationTestCase` + `StoreProfile` +
  `withLocales` + `$store->inScope(...)`). STUDY them. Tag `->group('integration-destructive')`.
- Profile rooted to include `catalog-attribute-storefront` + `catalog-attribute-index` +
  `markommerce/locale` + `marko/database-pgsql` + `markommerce/attribute-pgsql`. If a test exercises the
  storefront grid/component end-to-end it also needs `markommerce/catalog-storefront` (and its price-index
  dependency, since `ProductGridComponent` requires `ProductPriceIndexRepositoryInterface`); for pure
  filter/facet-query tests, resolving `AttributeFacetQuery` + `CategoryAssignmentService` +
  `AttributeProductListFilter` suffices. Root only what each scenario actually resolves.
- Be precise about expected row/count math under FULL materialization: every product gets a row at EVERY
  served signature (en + de here), including base-equal rows. Assert counts at a SPECIFIC
  `scope_signature` (the resolved one), not across all signatures, or counts will double.
- Seed: a category with several products; a facetable `select` attribute `color` (scopable on `locale`)
  with options + per-product values (some overridden for `locale:de`); a second facetable attribute
  (e.g. `size`) for the cross-attribute / disjunctive cases. Rebuild the attribute index (full
  materialization).
- Scenarios:
  - Filter `color=red` → listing returns only red products (count + ids).
  - Multi-value `color=red,blue` (OR) and `color=red&size=L` (AND across) narrow correctly.
  - Disjunctive facet: with `color=red` selected, the `color` facet still shows blue's count (its own
    filter excluded), while the `size` facet counts reflect the `color=red` constraint.
  - Scope: under `inScope(null,'de')`, `color` facet values/counts reflect the de-resolved values
    (e.g. `rot`), and `color=rot` filters correctly; under `en`, the base values.
  - Empty selection → full category listing + full facet counts (parity with no-filter listing).

## Requirements (Test Descriptions)
- [x] `it narrows the category listing to products matching a selected attribute value`
- [x] `it ORs multiple values within an attribute and ANDs across attributes`
- [x] `it computes a facet disjunctively while constraining other facets by the selection`
- [x] `it resolves facet values and filtering for the active scope`
- [x] `it returns the full listing and facet counts when no filters are selected`

## Acceptance Criteria
- Tests pass under `composer test:integration` against real Postgres using the harness schema.
- Filtering + disjunctive faceting + scope correctness demonstrated end-to-end.

## Implementation Notes
The test file was already scaffolded but used `ProductFactory::create()` to save products before
setting attribute values, then called `save()` again. The Marko `Repository::update()` method skips
companions that have `originalValues === []` (never hydrated), which silently dropped all attribute
values. Fixed by creating `Product` entities in-memory, setting all attribute values BEFORE the
single `save()` (insert) call, then assigning to the category via
`ProductCategoryAssignmentRepositoryInterface`. The `AttributeProductListFilter` and
`AttributeFacetQuery` implementations agreed on signature resolution (both use
`SignatureCandidateEnumerator`; the filter picks `candidates[0]`, the facet walks and checks index
presence — both converge to `''` at base scope and `locale:de` at de scope), so no signature-
disagreement was detected.
