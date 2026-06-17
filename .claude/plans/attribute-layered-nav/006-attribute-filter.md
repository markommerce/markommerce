# Task 006: `AttributeProductListFilter` — EXISTS constraints over the index

**Status**: completed
**Depends on**: 002, 003, 005
**Retry count**: 0

## Description
Implement the attribute filter contributor: given a `FilterSelection`, add `EXISTS` constraints over
the attribute index so the category listing is narrowed to products matching the selected attribute
values (AND across attributes, OR within an attribute), at each attribute's resolved scope signature.

## Context
- Place in `packages/catalog-attribute-storefront/src/`. `AttributeProductListFilter implements
  Markommerce\Catalog\…\ProductListFilterInterface` (task 003).
- Inject: `AttributeDefinitionRepositoryInterface` (resolve which selection keys are attribute codes +
  their `config['axes']`/type), `SignatureCandidateEnumerator` + `ScopeContext` (resolve the signature
  per attribute), and the index table name.
- **Reuse the shared EXISTS-clause builder from `catalog-attribute-index` (task 005)** — do NOT
  duplicate it. Call it with `$outerColumn = 'catalog_products.id'` (the listing query's product table —
  confirmed: `CategoryAssignmentService::paginatedProductsInCategory` builds the query against
  `catalog_products` joined to `catalog_product_category`). It produces an
  `EXISTS (SELECT 1 FROM catalog_product_attribute_index aei WHERE aei.product_id = catalog_products.id
  AND aei.attribute_code = ? AND aei.scope_signature = ? AND aei.value_text IN (?, ?, …))` fragment +
  bindings. This keeps "apply all filters" (listing) consistent with "apply all filters except X"
  (disjunctive facets). (Storefront depends on the index package — correct direction; the builder lives
  in the lower package.)
- `apply(RepositoryQueryBuilder $query, FilterSelection $selection)`: for each selection key that is a
  facetable/filterable attribute code, add one EXISTS constraint (values OR-ed via `IN`); multiple
  attributes AND together (separate EXISTS clauses). Apply each fragment via
  `$query->whereRaw($sql, $bindings)` — CONFIRMED: `RepositoryQueryBuilder::whereRaw(string $expression,
  array $bindings = []): static` exists and is chainable, delegating to the driver. The driver runs
  `IdentifierValidator::assertNoDangerousPatterns()` which only rejects `; -- /* */ \``; an EXISTS
  subquery contains none of these, so it passes. Do NOT use `raw()` (that EXECUTES and returns rows — it
  is not a chainable where). No-op for non-attribute keys / empty selection.
- Resolve the signature per attribute via `SignatureCandidateEnumerator::enumerate($axes, $scopeContext)`
  (walk-up candidates, most-specific first; `''` when `enumerate()` returns `[]` for non-scopable/empty
  axes) — must use the EXACT same resolution rule as task 005's facet query and match the index's
  materialized signatures (task 001). Pin this in a shared helper if practical so 005 and 006 cannot drift.

## Requirements (Test Descriptions)
- [x] `it adds an EXISTS constraint for a selected attribute value`
- [x] `it ORs multiple selected values for the same attribute via IN`
- [x] `it ANDs constraints across different selected attributes`
- [x] `it resolves the scope signature for a scopable attribute from the context`
- [x] `it uses the base signature for a non-scopable attribute`
- [x] `it is a no-op when the selection has no attribute keys`

## Acceptance Criteria
- Selected attribute values narrow the query (AND across attrs, OR within) at the resolved signature.
- The EXISTS-clause builder is shared with task 005's facet query.

## Implementation Notes
- `AttributeProductListFilter` placed in `packages/catalog-attribute-storefront/src/Filter/`
  implementing `Markommerce\Catalog\Filtering\ProductListFilterInterface`.
- Reuses `AttributeExistsClause` from `catalog-attribute-index` (no duplication).
- Signature resolution: takes first candidate from `SignatureCandidateEnumerator::enumerate()`
  (most-specific first); falls back to `''` when axes is empty or enumerate returns [].
- `resolveFilterableDefs()` queries `facetable = true` (same as task 005's facet query)
  so only facetable attributes are considered eligible for filtering.
- Unit tests use `SpyRepositoryQueryBuilder extends RepositoryQueryBuilder` (skips parent
  constructor, overrides `whereRaw` to capture calls) and `QueryableAttributeDefinitionRepository`
  from `catalog-attribute-index` tests (reused via autoload).
- Support class placed in `packages/catalog-attribute-storefront/tests/Support/`.
