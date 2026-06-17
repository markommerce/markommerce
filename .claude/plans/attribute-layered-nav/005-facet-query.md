# Task 005: `AttributeFacetQuery` — disjunctive facet counts over the index

**Status**: completed
**Depends on**: 001, 003
**Retry count**: 0

## Description
A query service that, for a category + active scope + applied filter selection, returns the facetable
attributes with their value→product-count, computed DISJUNCTIVELY (each facet applies all OTHER active
filters but not its own). Uses the full-materialized index (single resolved signature per attribute).

## Context
- Place in `packages/catalog-attribute-index/src/Facet/` (the index package owns index queries).
- Inject: `ConnectionInterface` (raw SQL), `AttributeDefinitionRepositoryInterface` (list facetable
  defs: `query()->where('entity_type','product')` + `facetable=true`; capture `type`, `config['axes']`),
  `SignatureCandidateEnumerator` + `ScopeContext` (resolve each attribute's signature),
  `FilterSelection` (passed per call).
- `facets(int $categoryId, FilterSelection $selection): list<Facet>` (a `Facet` value object: code,
  type/kind, `list<FacetValue>{value, count, selected}`). For each facetable attribute:
  - Resolve its signature S for the active scope. SIGNATURE RESOLUTION MUST MATCH THE READER + INDEX:
    call `SignatureCandidateEnumerator::enumerate($def->config()['axes'] ?? [], $scopeContext)` to get the
    resolution-ordered walk-up candidates (most-specific first), then pick the FIRST candidate
    (`$candidate->toString()`) for which the materialized index actually has rows at this category;
    if none, fall back to base `''`. This mirrors `IndexedAttributeReader::resolve()` (walk candidates,
    first present wins, else base). For non-scopable / empty-axes attributes `enumerate()` returns `[]`,
    so S = `''`. Do NOT assume a single served signature equals the context state — the enumerator's
    candidates are the walk-up paths (with the axis default filtered out), which is what the index
    materializes against (`ServedScopesProviderInterface::signatures($axes)`). With full materialization
    (task 001) every category product has a row at the resolved S.
  - NOTE: the simplest correct approach is to resolve S as `enumerate(...)[0]?->toString() ?? ''`
    (most-specific candidate) and rely on full materialization guaranteeing a row at every served
    signature; but if the most-specific candidate is NOT in the served set, walk down the candidate list
    until a served/present signature is found (test both: a context with an exact served match, and a
    context whose most-specific candidate is unserved and must fall to a less-specific one or base).
  - Count query: `SELECT value_text, COUNT(DISTINCT i.product_id) FROM catalog_product_attribute_index i
    JOIN catalog_product_category cpc ON cpc.product_id = i.product_id AND cpc.category_id = ?
    WHERE i.attribute_code = ? AND i.scope_signature = ? GROUP BY value_text` — PLUS, for DISJUNCTIVE
    counts, AND the EXISTS constraints for every OTHER selected attribute (i.e. `$selection->without(code)`).
    Mark a value `selected` if it's in `$selection->forKey(code)`.
- **Define the shared EXISTS-clause builder HERE** (in `catalog-attribute-index`, e.g.
  `Query/AttributeExistsClause`): a method like
  `build(string $outerColumn, string $attributeCode, array $values, string $signature, string $existsAlias = 'aei'): array{sql: string, bindings: list}`
  that returns the `EXISTS (SELECT 1 FROM catalog_product_attribute_index <alias> WHERE
  <alias>.product_id = <outerColumn> AND <alias>.attribute_code = ? AND <alias>.scope_signature = ? AND
  <alias>.value_text IN (?, ?, …))` SQL fragment + bindings. The `$outerColumn` is REQUIRED and differs
  per caller: the storefront listing filter (task 006) passes `catalog_products.id`; the facet query
  (task 005) passes its own product column (e.g. `i.product_id` where `i` is the facet's outer index
  alias joined to the category). The inner EXISTS alias MUST differ from any outer alias to avoid
  self-join name collisions (e.g. outer `i`, inner `aei`) — make the inner alias a parameter with a
  distinct default. Validate `$outerColumn` against the qualified-identifier pattern
  `/^[a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*$/`; parameterize all values + signature.
  This facet query uses it for the "other filters"; the storefront filter (task 006) ALSO reuses it
  (storefront depends on this package, so the builder lives in the lower package — correct dependency
  direction). Resolve each attribute's signature via the candidate enumerator (see above) so it matches
  the materialized rows. Cover with a unit test that the same builder produces the right correlation for
  both outer columns.
  - Term facets via `value_text`. Numeric (`value_number`) facets: return simple min/max (+ value
    counts if discrete); rich bucketing deferred.
- Validate identifiers; parameterize values. Read-only.

## Requirements (Test Descriptions)
- [x] `it builds an EXISTS clause correlated to the given outer product column with a distinct inner alias`
- [x] `it returns facet value counts for a facetable attribute in a category`
- [x] `it counts distinct products per value at the resolved scope signature`
- [x] `it computes a facet disjunctively ignoring that facet's own selected values`
- [x] `it applies other attributes' selected filters when counting a facet`
- [x] `it marks selected values in the returned facet`
- [x] `it only returns facetable attributes`

## Acceptance Criteria
- Disjunctive facet counts per facetable attribute for a category + scope, at the resolved signature.
- Shares the EXISTS constraint builder with the listing filter (task 006) — no duplicated filter logic.

## Implementation Notes

### Files created
- `packages/catalog-attribute-index/src/Query/AttributeExistsClause.php` — shared EXISTS-clause builder; validates outer column with `/^[a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*$/`; parameterizes all values; inner alias defaults to `aei`
- `packages/catalog-attribute-index/src/Facet/FacetValue.php` — readonly value object: `value`, `count`, `selected`
- `packages/catalog-attribute-index/src/Facet/Facet.php` — readonly value object: `code`, `type`, `list<FacetValue>`
- `packages/catalog-attribute-index/src/Facet/AttributeFacetQuery.php` — main query service; resolves facetable defs via `query()->where('entity_type','product')->where('facetable',true)`; walks `SignatureCandidateEnumerator` candidates then queries index to find first served signature; builds disjunctive EXISTS constraints via `AttributeExistsClause`; marks selected values via `FilterSelection::forKey()`
- `module.php` — added `AttributeExistsClause` and `AttributeFacetQuery` bindings

### Tests created
- `tests/Unit/Query/AttributeExistsClauseTest.php` — unit tests for the builder (3 tests)
- `tests/Unit/Facet/AttributeFacetQueryTest.php` — unit test for facetable-only filter (1 test)
- `tests/Feature/AttributeFacetQueryTest.php` — 5 integration tests against real Postgres covering: basic counts, scoped signature resolution, disjunctive counts, other-attribute cross-filter, selected value marking

### Key design decisions
- Signature resolution: walk `SignatureCandidateEnumerator::enumerate()` candidates, query index to find first with rows at this category, fall back to `''` — exactly mirrors `IndexedAttributeReader`
- Disjunctive: for each attribute, call `$selection->without($code)` to get other filters, apply as EXISTS constraints
- Other-attribute signature resolution: re-queries the definition for each other selected attribute to resolve its correct signature for the EXISTS sub-clause
- The `AttributeExistsClause` is reusable by task 006 (storefront filter) — outer column is parameterized
