# Plan: Attribute Layered Navigation (Custom Attributes — Phase 5)

## Created
2026-06-16

## Status
completed

## Objective
Layered navigation over the Phase-4 attribute index: filter the catalog product listing by selected
attribute values and compute faceted value counts (disjunctive) for the current category + scope,
with a storefront facet sidebar. Amends the Phase-4 indexer to full per-signature materialization so
facet/filter queries are single-signature `WHERE`/`GROUP BY`.

## Related Issues
none

## Discovery Notes
Phase 5 of the `custom-attributes` meta-plan. Branched from `develop` (Phases 1–4 + the indexer fix).
Grounded in the actual code:
- **Phase-4 index** (`catalog-attribute-index`): EAV `ProductAttributeIndexEntry`
  (product_id, attribute_code, scope_signature, value_text/number/bool, value_kind) with indexes
  `(scope_signature, attribute_code, value_text)`, `(…, value_number)`, `(product_id)`. The
  `AttributeIndexer` currently SKIPS emitting a scoped row when the value equals base
  ("skip-redundant") — which breaks single-signature faceting. **Phase 5 amends it to full
  per-signature materialization.**
- **`criteria`** is pagination + sort only — NO filter abstraction. Filtering belongs in the listing
  query, applied before pagination (like sort orders).
- **Listing extension point**: `CategoryAssignmentService::paginatedProductsInCategory($categoryId,
  ResolvedPaginationOptions)` builds the product query and calls `$options->sortOrder->prepareQuery($query)`.
  The proven pattern is `CategorySortOrderInterface` (`key/label/prepareQuery/sortFields`) + registry,
  surfaced by `ProductGridComponent`. Attribute filters mirror this via `EXISTS (SELECT 1 FROM
  catalog_product_attribute_index …)`.
- **Query builder** supports `leftJoin/where/whereIn/groupBy/having/raw/count` + `EXISTS` subqueries.
- **Scope at query time**: `ScopeContext` + `SignatureCandidateEnumerator` give the resolution-ordered
  signatures (identical to the index reader), so queries target the attribute's resolved signature.
- **Storefront**: `catalog-storefront` `CategoryController` + `ProductGridComponent` render the
  category page and expose sort options; `catalog-storefront-scope` overrides via `#[Preference]`.

Resolved decisions (clarification):
- **Full per-signature materialization** (amend Phase 4): every product gets a row per its attribute's
  served signatures (+ base). Facet/filter queries target a single `scope_signature` → trivial fast
  `GROUP BY`. (Magento flat-per-store style.) Requires reindex + a Phase-4 test adjustment.
- **Storefront UI included**: a facet sidebar on the category page + filter selections via query params.
- **Disjunctive facets**: each facet's counts apply all OTHER active filters but not its own.
- Filter combination: AND across attributes, OR within an attribute (multi-select).

## Scope

### In Scope
- Amend `AttributeIndexer` → full per-signature materialization (drop skip-redundant).
- `catalog`: generic `ProductListFilterInterface` + `ProductListFilterRegistry` + a `FilterSelection`
  value object; apply active filters in the listing query before pagination (attribute-agnostic).
- `catalog-attribute-index`: `AttributeFacetQuery` (disjunctive facet value counts per facetable
  attribute, for a category + scope + applied selection) over the materialized index.
- New `markommerce/catalog-attribute-storefront`: `AttributeProductListFilter` (EXISTS constraints
  for selected attribute values, scope-resolved) + a layered-nav assembler (facets + active filters)
  + the category-page integration.
- `catalog-storefront`: category controller reads filter query params → `FilterSelection`; facet
  sidebar UI (values + counts + selected state + toggle links).
- Integration tests, module wiring, READMEs, docs.

### Out of Scope
- Full-text search (Phase 6) / search-engine drivers.
- Range-facet bucketing UI beyond simple min/max (numeric/date facets: basic support; rich buckets later).
- Faceting on non-product entities.
- Auto-invalidation/async reindex (manual rebuild remains; live fallback unchanged).

## Success Criteria
- [x] The `AttributeIndexer` materializes a row per product per served signature (+ base); facet/filter queries use a single `scope_signature`.
- [x] Selecting an attribute value filters the category product listing (AND across attributes, OR within one), applied before pagination, scope-correct.
- [x] Facet value counts are computed disjunctively (each facet ignores its own selection) for the current category + scope.
- [x] The category page renders a facet sidebar (values + counts + selected) and reflects filter selections from query params.
- [x] `catalog` stays attribute-agnostic (generic filter registry); the attribute logic lives in `catalog-attribute-storefront`.
- [x] All tests passing (unit + integration); coverage ≥ 80%; phpcs / phpstan level 8 clean.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Amend `AttributeIndexer` → full per-signature materialization | - | completed |
| 002 | Scaffold `markommerce/catalog-attribute-storefront` | - | completed |
| 003 | `catalog`: `FilterSelection` + `ProductListFilterInterface` + registry | - | completed |
| 004 | Apply active filters in the catalog listing query (before pagination) | 003 | completed |
| 005 | `AttributeFacetQuery` — disjunctive facet counts over the index | 001, 003 | completed |
| 006 | `AttributeProductListFilter` (EXISTS constraints, scope-resolved; reuses task-005 builder) | 002, 003, 005 | completed |
| 007 | Layered-nav assembler + category controller filter-param parsing | 004, 005, 006 | completed |
| 008 | Facet sidebar UI (component data + Latte) | 007 | completed |
| 009 | Module wiring (catalog-attribute-storefront + catalog registry binding) | 005, 006 | completed |
| 010 | Integration tests (filter narrowing, disjunctive facets, scope) | 004, 005, 006, 007, 009 | completed |
| 011 | READMEs + docs | 001-010 | completed |

## Architecture Notes
- **Full materialization (task 001):** drop the `if ($value === $baseValue) continue;` skip in
  `AttributeIndexer::indexChunk`; always emit a row for each served signature of a scopable attribute
  (+ the base `''` row). Non-scopable attributes still get only the base row. Result: under active
  signature S, every product has exactly one row at S for a scopable attribute → facet/filter =
  `WHERE scope_signature = S AND attribute_code = ? …`. The live-fallback reader is unaffected (its
  top candidate is now always present for scopable attrs). Re-run reindex in integration tests.
- **Generic filter hook (tasks 003–004):** `FilterSelection` = `array<string, list<string>>`
  (filterKey → selected values). `ProductListFilterInterface::apply(RepositoryQueryBuilder $query,
  FilterSelection $selection): void` (no-op when its keys are absent). `ProductListFilterRegistry`
  holds contributors. `CategoryAssignmentService::paginatedProductsInCategory` gains a
  `FilterSelection` argument (default empty) and, before pagination, iterates the registry calling
  `apply($query, $selection)`. `catalog` defines the contract only — no attribute dependency.
- **Attribute filter (task 006, in catalog-attribute-storefront):** `AttributeProductListFilter
  implements ProductListFilterInterface`. For each attribute selection, resolve the attribute's
  signature for the active `ScopeContext` via `SignatureCandidateEnumerator::enumerate($axes, $context)`
  (the resolution-ordered walk-up candidates, most-specific first; `''` when it returns `[]` for
  non-scopable/empty axes) — the SAME rule the reader + task 005 use, so all three agree. Add
  `EXISTS (SELECT 1 FROM catalog_product_attribute_index aei WHERE aei.product_id = catalog_products.id
  AND aei.attribute_code = ? AND aei.scope_signature = ? AND aei.value_text IN (…))` (OR within an
  attribute via `IN`; AND across attributes via multiple EXISTS). Applies each via
  `RepositoryQueryBuilder::whereRaw($sql, $bindings)` (CONFIRMED chainable; `raw()` is NOT — it executes).
- **Facet query (task 005, in catalog-attribute-index):** `AttributeFacetQuery::facets($categoryId,
  ScopeContext, FilterSelection): list<Facet>`. For each facetable attribute definition: run a
  COUNT(DISTINCT product_id) GROUP BY value over the index joined to the category, at the attribute's
  resolved signature, applying every OTHER selected filter (disjunctive — exclude this attribute's own
  selection) as EXISTS sub-constraints. Returns value→count + the selected flag. Term facets via
  `value_text`; numeric via `value_number` (simple min/max for now). Reuse the task-006 filter
  constraints for the "other filters" so filter logic isn't duplicated.
- **Storefront (tasks 007–008):** category controller parses the bracketed `filter` array query param
  (`?filter[color][]=red&filter[color][]=blue&filter[size][]=L`, read via `$request->query('filter')`
  — PHP expands brackets into nested arrays, namespaced so it never collides with `page`/`size`/`sort`/`view`)
  → `FilterSelection`; passes it to the listing service (filtered products)
  and the facet assembler (facets). Component data gains `facets` (groups: label, values w/ count +
  selected + toggle URL) + `activeFilters`. Facet sidebar Latte template renders them. Option labels
  resolved per scope via the `attribute-scope` package's `ScopedOptionLabelResolver` (namespace
  `Markommerce\AttributeScope`, NOT catalog-attribute-scope) where the attribute is select/multiselect.
  WIRING: do NOT add a competing `#[Preference]` — `catalog-storefront-scope` already replaces
  `ProductGridComponent`. Instead extend `catalog-storefront`'s `ProductGridComponent` + `ProductGridData`
  in place (optional/nullable `LayeredNavigation` dependency so `catalog-storefront` stays decoupled),
  and forward the new `ProductGridData` fields through `ScopedProductGridComponent::data()` so the scoped
  path doesn't drop facets.
- Packages: facet QUERY in `catalog-attribute-index`; filter contributor + assembler + storefront
  integration in new `catalog-attribute-storefront`; generic filter registry in `catalog`; UI in
  `catalog-storefront`. Standards: PHP 8.5, no `final`, strict types, `@throws`, constructor injection.

## Risks & Mitigations
- **Amending Phase 4 (task 001):** index grows (products × served signatures × attributes); bounded
  by the served-scopes cap. The skip lives in `AttributeIndexer::runScopedPasses()` (not `indexChunk`).
  There is NO existing unit test asserting "skip when equal to base" to invert — ADD a new one. The
  Phase-4 INTEGRATION test `AttributeIndexIntegrationTest` asserts `toHaveCount(2)` for `color`; the
  profile serves locales en+de, so full materialization adds a `locale:en` base-equal row → the count
  becomes 3. UPDATE that assertion (and audit sibling scenarios for the same drift). Re-run reindex in
  integration tests; the `IndexedAttributeReader` and its tests stay green. Verify in the playground after.
- **Catalog stays attribute-agnostic:** the generic filter registry must not reference attribute
  types; verify `catalog` has no new dependency on `catalog-attribute*`.
- **Disjunctive faceting correctness:** each facet excludes its OWN selection but applies others —
  reuse the single filter-constraint builder (task 006) so "apply all filters except X" is consistent
  with "apply all filters" used by the listing. Cover with a multi-filter integration test.
- **Listing service signature change (task 004):** adding a `FilterSelection` param to
  `paginatedProductsInCategory` — default empty to preserve existing callers; update callers + tests.
- **Query-builder EXISTS support:** RESOLVED — `RepositoryQueryBuilder::whereRaw(string $expression,
  array $bindings = []): static` exists, is chainable, and delegates to the pgsql driver, which only
  rejects `; -- /* */ \`` via `IdentifierValidator::assertNoDangerousPatterns()` (an EXISTS subquery
  passes). Use `whereRaw` for the EXISTS; do NOT use `raw()` (it executes and returns rows).
- **Signature-resolution agreement (001 ↔ 005 ↔ 006):** the index materializes per `ServedScopesProvider`
  served signatures; query-time resolution uses `SignatureCandidateEnumerator::enumerate($axes, $context)`
  (walk-up candidates, most-specific first, axis-default filtered out). Tasks 005 and 006 MUST use the
  identical resolution rule (and ideally a shared helper) so filter and facet target the same row the
  reader would. Cover a context whose most-specific candidate is served AND one that must fall to a
  less-specific candidate or base.
- **Shared EXISTS builder correlation:** the builder (in catalog-attribute-index, task 005) takes the
  outer product column as a REQUIRED arg (`catalog_products.id` for the listing filter; the facet query's
  own outer alias for disjunctive sub-constraints) and uses a distinct inner alias to avoid self-join
  collisions in the facet query.
