# Task 007: `AttributeIndexer` (EAV rows per served signature via the scoped accessor)

**Status**: complete
**Depends on**: 002, 003, 006
**Retry count**: 0

## Description
Implement the attribute indexer: for each product, materialize EAV rows for every filterable/facetable
attribute, already-resolved for the base scope and each served signature, via the Phase-3 scoped
accessor. Built on the shared `AbstractIndexer`.

## Context
- Place in `packages/catalog-attribute-index/src/AttributeIndexer.php`. Extend
  `Markommerce\Indexer\AbstractIndexer` (task 003); implement `allIds()` (all product ids via
  `ProductRepository->query()`) and `indexChunk(array $ids)`.
- Inject: `ProductRepositoryInterface` (load the chunk), `AttributeDefinitionRepositoryInterface`
  (to LIST filterable/facetable custom definitions for `entityType='product'` + their `type`,
  `scopable`, and `config()['axes']`), `ScopedProductAttributeAccessor` (Phase 3 —
  `resolve(Product, code)` for the active scope), the core scope-pass helper +
  `ServedScopesProviderInterface`, and `ProductAttributeIndexRepository` (task 006).
  - Confirm `AttributeDefinitionRepositoryInterface` exposes a way to list definitions by entity type
    (it `extends RepositoryInterface` with `query()`); if no `entity_type`+flag filter exists, use the
    repository `query()` builder (`where('entity_type','product')` and the `filterable`/`facetable`
    boolean columns). Do NOT add new methods to the Phase-1 interface unless required.
- `indexChunk`:
  - Load the products for `$ids`.
  - Determine the attribute codes to index: enumerate `AttributeDefinitionRepositoryInterface`
    definitions for `entityType = 'product'` flagged `filterable === true` OR `facetable === true`.
    Capture each definition's `type` and `scopable` flag and its `config()['axes']` (default `[]`).
    NOTE: `ProductAttributeDefinitions::findByCode()` is single-code only and merges in statics — to
    LIST all custom definitions use the `AttributeDefinitionRepositoryInterface` query directly
    (statics are native columns, indexed elsewhere — exclude them).
  - **Per-attribute signature set (CRITICAL for read consistency).** Do NOT use one global cartesian.
    For each indexed attribute, the set of scoped passes is `ServedScopesProviderInterface::signatures(
    $def->config()['axes'] ?? [])` — the cartesian over THAT attribute's own axes only. A non-scopable
    attribute (or one with empty `axes`) gets NO scoped passes — index it once under the base `''`
    signature only (it resolves identically in every scope; `setScoped` is forbidden for non-scopable).
    This MUST match how the reader (task 008) and the live `ScopeWalker`/`SignatureCandidateEnumerator`
    enumerate candidate signatures for the same attribute, or reads will fall through to the live path.
  - Build rows. For the base pass (cleared scope, `null` from the scope-pass helper → `scope_signature
    = ''`): for each product × each indexed code, call `ScopedProductAttributeAccessor::resolve` and
    emit a typed row. For the scoped passes: group attributes by their axis set so the scope-pass
    helper runs each distinct signature once; in each pass resolve only the attributes whose axes the
    signature covers, emitting rows tagged with `$signature->toString()`. (Multiselect → one row per
    member; null/absent resolved value → no row.)
  - To avoid redundant scoped rows: only emit a scoped-signature row when its resolved value DIFFERS
    from the base-`''` value for that product+code (a scoped pass that resolves to the same value as
    base adds no information — the reader's candidate walk-up falls back to base anyway). Document this.
  - `ProductAttributeIndexRepository::replaceForProducts($ids, $rows)` (delete + insert).
  - Return the row count.
- Resolution casing: `ScopedProductAttributeAccessor::resolve` returns the already-cast value
  (string for text/decimal, int for int, bool for bool, array for multiselect, option value for
  select). Route to the typed column per task 006; multiselect explodes member-by-member.

## Requirements (Test Descriptions)
- [x] `it indexes only filterable or facetable attributes`
- [x] `it writes a base-signature row for each indexed attribute value`
- [x] `it writes a per-signature row with the scope-resolved value for a scopable attribute`
- [x] `it indexes a non-scopable attribute once under the base signature`
- [x] `it emits one row per member for a multiselect value`
- [x] `it omits a row when the resolved value is null`
- [x] `it replaces a product's existing index rows on reindex`

## Acceptance Criteria
- Rows are resolved per served signature via `ScopedProductAttributeAccessor`; only filterable/facetable.
- Reuses the shared `AbstractIndexer` lifecycle + scope-pass helper + served-scopes provider.

## Implementation Notes
- `AttributeIndexer` extends `AbstractIndexer` and lives at `packages/catalog-attribute-index/src/AttributeIndexer.php`.
- `allIds()` uses `query()->selectRaw('id')->get()` then maps to `int`.
- `indexChunk` loads products via `query()->whereIn('id', $ids)->getEntities()`, queries defs via `query()->where('entity_type', '=', 'product')->getEntities()`, then filters to filterable|facetable and non-Column-backed.
- Per-attribute signature sets: non-scopable or empty `axes` → empty list (base-only); scopable → `servedScopesProvider->signatures($axes)`.
- Scoped passes are grouped by serialized signature set to run each distinct set once with `ScopePassRunner::each()`.
- Skip-redundant: scoped rows are only emitted when the resolved value differs from the base value for that product+code.
- Two new test support fakes added: `FakeDefQueryBuilder` and `QueryableAttributeDefinitionRepository` (both in `tests/Support/`), replacing the upstream `FakeAttributeDefinitionRepository` which throws on `query()`.
