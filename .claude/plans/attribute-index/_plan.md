# Plan: Attribute Read-Model Index + Shared Indexer Core (Custom Attributes — Phase 4)

## Created
2026-06-15

## Status
completed

## Objective
Build a resolved read-model INDEX for product attribute values — EAV facet rows materialized
per served scope, optimized for layered-navigation filtering/faceting — with a
correctness-preserving live fallback. Extract a shared `markommerce/indexer` core (chunked
rebuild, served-scope enumeration, per-scope passes, bulk persist, CLI template) and refactor the
existing `catalog-price-index` onto it, proving the abstraction with two consumers.

## Related Issues
none

## Discovery Notes
Phase 4 of the `custom-attributes` meta-plan. Branched from `feature/scoped-attributes` (carries
Phases 1–3). Grounded in the existing price indexer:
- **`catalog-price-index` is the template.** `PriceIndexer` (`reindexProducts(ids)` /
  `reindexProduct(id)` / `rebuildAll(chunk)`): chunked product load → base pass (cleared scope) →
  one pass per served market (set scope, batch-resolve, write overrides) → single bulk
  `INSERT…ON CONFLICT`. Entity `ProductPriceIndexEntry` = one row per product + a `scopes` JSONB of
  per-market overrides (`HasScopes`). Read via sort-order JOINs. Scope context saved/restored
  around reindex.
- **Reuse pattern already exists:** `catalog-price-index-market` does NOT duplicate the indexer —
  it injects a `ScopedIndexedMarketsProvider` (via `#[Preference]`) + registers the scoped field.
  "Which scopes to materialize" is a pluggable provider.
- **Trigger is MANUAL only (verified):** no observers/events anywhere in the price-index packages;
  the sole entry point is the `catalog:price-index:rebuild` CLI command. Phase 4 matches this —
  manual CLI rebuild, no auto-invalidation observers.
- **Live-fallback source ready:** `ScopedProductAttributeAccessor::resolve(Product, code)` is the
  per-scope resolution the index caches and falls back to (Phases 2–3).
- **Multi-axis is new:** price indexes a single axis (market); attributes span the cartesian product
  of declared axes. `ScopeRegistry`/`ScopeHierarchy` expose axis paths; `ScopeSignature` serializes.

Resolved decisions (clarification):
- **Shared core + refactor + new index** (all three): build `markommerce/indexer`, refactor
  `catalog-price-index` onto it, build `catalog-attribute-index` on it. Two real consumers.
- **EAV facet rows** for the attribute index (best for layered-nav filtering/faceting): one row per
  `(product_id, attribute_code, scope_signature)` with typed columns, materialized ALREADY-RESOLVED
  per served signature → reads are plain indexed `WHERE`/`GROUP BY`, no JSON/COALESCE. Multiselect
  explodes to one row per member.
- **Manual/CLI trigger + live fallback** (no observers this phase) — matching price-index.
- The core abstracts the indexer LIFECYCLE + UTILITIES, NOT the row shape (price = per-product +
  `scopes` JSONB; attribute = EAV rows). Both reuse served-scopes enumeration, per-scope passes,
  bulk persist, chunked rebuild, and the CLI template.

## Scope

### In Scope
- New `markommerce/indexer` kernel: `IndexerInterface` + `AbstractIndexer` (chunked rebuild/reindex),
  `ServedScopesProviderInterface` + cartesian provider (bounded), a scope-pass helper
  (scope-context save/restore per signature), an `IndexRepository` base (bulk persist / delete-by-ids
  / truncate), an `IndexerRegistry` (name → indexer), and a single unified
  `index:rebuild [name?] --chunk` CLI command (rebuild one or all registered indexes,
  Magento-`indexer:reindex`-style).
- Refactor `catalog-price-index` onto the core (`PriceIndexer` composes the core utilities;
  registers as `price` in the `IndexerRegistry`; its `RebuildPriceIndexCommand` is removed in favor
  of the unified `index:rebuild` — the one intentional surface change). Indexing behavior preserved
  (existing tests are the guard).
- New `markommerce/catalog-attribute-index`: `ProductAttributeIndexEntry` EAV entity + index
  repository (delete-by-product + batch insert), `AttributeIndexer` (resolves filterable/facetable
  attributes per served signature via `ScopedProductAttributeAccessor`, EAV rows, multiselect
  exploded), a live-fallback reader, registration as `attribute` in the `IndexerRegistry` (rebuilt via
  the unified `index:rebuild attribute`), module wiring, integration tests, READMEs.

### Out of Scope
- Faceting/filter QUERY API + layered-navigation UI (Phase 5 consumes this index).
- Full-text search (Phase 6).
- Auto-invalidation observers / async reindex (manual CLI only this phase; live fallback covers staleness).
- Indexing static (sku/name/priceAmount) attributes (native columns; handled elsewhere).
- Generated-column projections / search-engine drivers (later phases).

## Success Criteria
- [ ] `markommerce/indexer` provides reusable lifecycle/utilities; both indexers consume it.
- [ ] `catalog-price-index` refactored onto the core with its existing test suite still green (no behavior change).
- [ ] Rebuilding the attribute index materializes one EAV row per (product, filterable/facetable code, served signature) with the already-resolved value in the typed column; multiselect → one row per member.
- [ ] Layered-nav-style query — `WHERE scope_signature=? AND attribute_code=? AND value_text=?` and `GROUP BY value_text` — runs against indexed columns (no JSON/COALESCE).
- [ ] The live-fallback reader returns the indexed value when present and falls back to `ScopedProductAttributeAccessor::resolve()` when the row is absent/stale (correctness independent of index freshness).
- [ ] A single unified `index:rebuild [name?]` command rebuilds one or all registered indexes (`price`, `attribute`); the legacy `catalog:price-index:rebuild` is removed (clean rename).
- [ ] All tests passing (unit + integration); coverage ≥ 80%; phpcs / phpstan level 8 clean.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Scaffold `indexer` + `catalog-attribute-index` packages | - | completed |
| 002 | `ServedScopesProviderInterface` + cartesian provider (core) | 001 | completed |
| 003 | `IndexerInterface` + `AbstractIndexer` + scope-pass helper (core) | 001, 002 | completed |
| 004 | `IndexRepository` base + `IndexerRegistry` + unified `index:rebuild` command (core) | 001 | completed |
| 005 | Refactor `catalog-price-index` onto the core (behavior-preserving) | 003, 004 | completed |
| 006 | `ProductAttributeIndexEntry` EAV entity + index repository | 001, 004 | completed |
| 007 | `AttributeIndexer` (EAV rows per served signature via the scoped accessor) | 002, 003, 006 | completed |
| 008 | Live-fallback reader (`IndexedAttributeReader`) | 006 | completed |
| 009 | Register the attribute indexer (`attribute`) in the `IndexerRegistry` | 004, 007 | completed |
| 010 | Module wiring (catalog-attribute-index) | 006, 007, 008, 009 | completed |
| 011 | Integration tests (EAV rebuild, filter/facet query, live fallback) | 007, 008, 010 | completed |
| 012 | READMEs (indexer + catalog-attribute-index) + docs | 001-011 | completed |

## Architecture Notes
- **Shared core abstracts LIFECYCLE + UTILITIES, not row shape.** `AbstractIndexer` owns chunked
  `rebuildAll`/`reindex` (enumerate ids → chunk → `indexChunk($ids)` → persist), delegating row
  PRODUCTION to the subclass. Utilities the subclass composes:
  - `ServedScopesProviderInterface::signatures(array $axes): list<ScopeSignature>` — cartesian product
    of the GIVEN attribute's axis paths (NOT one global cartesian — see below), bounded with a
    documented cap + loud log when capped; `[]` when `$axes` is empty (global-only). The default
    provider delegates to / replicates the kernel `SignatureCandidateEnumerator` semantics so the
    materialized `ScopeSignature::toString()` values are byte-identical to the live resolution path.
    Overridable via `#[Preference]` (mirrors `IndexedMarketsProviderInterface`).
  - A scope-pass helper that, given signatures + a callback, save/restores the FULL multi-axis
    `ScopeContext` state (`state()`/`clearAll()`/re-apply `in()`), runs a base pass (cleared scope,
    callback receives `null` — `ScopeSignature` cannot represent the empty signature), then one pass
    per signature; restores in `finally` (incl. on exception). The price indexer's single-axis
    `get('market')`/`clear('market')` save/restore is a special case of this.
  - `IndexRepositoryInterface` + base: `deleteByEntityIds(ids)`, `truncate()`, and a Postgres bulk
    upsert/insert helper (mirrors `ProductPriceIndexRepository::upsertMany`, raw `::jsonb`/multi-row
    `INSERT`). Postgres SQL in the index repos matches the price-index precedent (no `-pgsql` split).
  - `AbstractRebuildIndexCommand` — CLI template taking an `IndexerInterface` + a `--chunk` option.
- **Price refactor (consumer 1):** `PriceIndexer` keeps its per-product entry + `scopes`-override
  accumulation but delegates chunking, served-scope enumeration (its markets provider adapts to the
  new `ServedScopesProvider` OR stays as-is feeding signatures), scope-pass save/restore, and bulk
  persist to the core. `RebuildPriceIndexCommand` extends the CLI template. **All existing
  price-index + price-index-market tests must stay green** — they are the behavior guard.
- **Attribute index (consumer 2):** `ProductAttributeIndexEntry` `#[Table('catalog_product_attribute_index')]`
  columns: `id`, `product_id`, `attribute_code`, `scope_signature` (string; `''` for the global/base
  row), `value_text` (nullable), `value_number` (numeric nullable), `value_bool` (nullable),
  `value_kind` (attribute type code, for read casting). Indexes declared via class-level `#[Index]`
  attributes (CONFIRMED expressible — `#[Index(name, columns, unique?)]` is used in-repo, e.g.
  `CategoryTreeNode`, both unique and non-unique composite):
  `(scope_signature, attribute_code, value_text)`, `(scope_signature, attribute_code, value_number)`,
  `(product_id)`. Each row holds the ALREADY-RESOLVED value for its signature.
  `AttributeIndexer`: for a product chunk, delete existing rows, then index every filterable/facetable
  CUSTOM attribute. **Signatures are PER-ATTRIBUTE** — for each attribute the scoped passes are the
  cartesian over THAT attribute's own `config()['axes']` (via the served-scopes provider /
  `SignatureCandidateEnumerator`), NOT a global cartesian; non-scopable / no-axes attributes are
  indexed once under base `''`. A scoped row is only emitted when its resolved value DIFFERS from base
  (else the reader's candidate walk-up falls back to base). Reindex = delete-by-product + insert.
- **Live fallback:** `IndexedAttributeReader::resolve(Product, code)` enumerates candidate signatures
  via the kernel `SignatureCandidateEnumerator` (the SAME enumerator `ScopeWalker::walk` uses), looks
  up index rows for each candidate in resolution order (first match wins, then base `''`); present →
  return the reconstructed typed value (multiselect → collect member rows); none → fall back to
  `ScopedProductAttributeAccessor::resolve`. Mirroring the live enumerator is what keeps the indexed
  signature and the read signature identical — otherwise reads silently fall through. Correctness is
  independent of index freshness (the meta-plan's headline property).
- Standards: PHP 8.5, no `final`, `declare(strict_types=1)`, constructor injection, `@throws`.

## Risks & Mitigations
- **Price-index refactor regression:** the abstraction must fit price's "mutate one entry across
  passes" accumulation. Mitigation: the core delegates row PRODUCTION to the subclass (utilities, not
  a rigid template); refactor keeps all price-index + price-index-market tests green (guard) — run
  them after the refactor (task 005).
- **Cartesian scope explosion:** declared axes × paths can blow up index size. Mitigation: bound the
  served-scopes provider with a documented cap + `log()`; default global-only when no axes;
  overridable via Preference. Document the bound (no silent truncation).
- **Typed value storage / casting:** resolved values are typed (text/int/decimal/bool/multiselect).
  Store into typed columns by the attribute's type; read-cast via `value_kind`. decimal as
  precision-safe string in `value_text` or numeric in `value_number` — decide per type and document.
- **Harness entity provisioning:** the EAV index table provisions from the entity; integration tests
  build a `StoreProfile` rooted to include it (mirror price-index Feature tests). Replicate any
  explicit `linkExtenders` need if companions are involved (none expected — the index entity is
  standalone, not a companion).
- **Multiselect unique/replace:** variable rows per product → reindex deletes the product's rows then
  inserts; no unique-conflict upsert needed. Ensure delete-by-product is scoped to the index table.
- **Signature-string drift (index vs read) — HEADLINE CORRECTNESS RISK:** if the indexer materializes
  rows under a different `scope_signature` string than the reader computes, EVERY read falls through to
  the live fallback and the index is silently dead. Mitigation: both the served-scopes provider (write
  side) and the reader (read side) derive signatures from the kernel `ScopeSignature`/
  `SignatureCandidateEnumerator` (ksorted `axis:path|...` serialization, walk-up candidate order),
  per-attribute axes on both sides. Task 008 includes an explicit reader-vs-live agreement test.
- **`#[Index]` confirmed expressible:** earlier-phase doubt was unfounded — `Marko\Database\Attributes\Index`
  supports composite unique AND non-unique indexes (used in `CategoryTreeNode`, `ProductCategoryAssignment`).
  No raw emitter/migration needed for the layered-nav indexes.
