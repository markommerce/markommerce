# Task 005: Refactor `catalog-price-index` onto the shared core (behavior-preserving)

**Status**: pending
**Depends on**: 003, 004
**Retry count**: 0

## Description
Refactor the existing `catalog-price-index` to consume `markommerce/indexer`: `PriceIndexer` reuses
the core chunking + scope-pass helper + served-scopes + bulk persist, and `RebuildPriceIndexCommand`
extends the abstract CLI command. This proves the shared core with a second consumer. **Behavior must
not change** — the existing price-index + price-index-market test suites are the guard.

## Context
- Files: `packages/catalog-price-index/src/PriceIndexer.php`,
  `packages/catalog-price-index/src/Command/RebuildPriceIndexCommand.php`,
  `packages/catalog-price-index/module.php`, and `packages/catalog-price-index/composer.json`
  (add `markommerce/indexer` require).
- **Interface-name mismatch is real — prefer COMPOSE over EXTEND.** The core `IndexerInterface`
  (task 003) is `reindex(ids)`/`reindexOne(id)`/`rebuildAll($chunk)`, but `PriceIndexerInterface` is
  `reindexProducts(ids)`/`reindexProduct(id)`/`rebuildAll($chunk)` — the names DON'T align, and the
  price indexer ACCUMULATES one entry per product MUTATED across passes (base sets `amount`/`currency`;
  each market pass `setOverride`s the SAME entry) before a single `upsertMany`. This accumulation does
  not match the per-pass-emits-rows shape of `indexChunk`. **Lowest-risk path: `PriceIndexer` COMPOSES
  the core utilities** (inject the scope-pass helper + the core bulk-repo helper) rather than extending
  `AbstractIndexer`, keeping its own `doReindexProducts` accumulation loop. Only extend `AbstractIndexer`
  if `indexChunk` can cleanly host the accumulate-then-upsert pattern; the worker chooses but MUST keep
  every existing test green. Document the choice in the implementation notes.
- Delegations:
  - per-market scope passes → the core scope-pass helper. NOTE the helper save/restores the FULL
    multi-axis state (`state()`/`clearAll()`), a superset of the price indexer's current single-axis
    `get('market')`/`clear('market')` save/restore — behavior-equivalent for the single-axis market case.
    The helper's base pass passes `null`; the price indexer's base pass (cleared market) maps to it.
  - served markets → **keep `IndexedMarketsProviderInterface`** and map each market string to a
    single-axis `market:$market` `ScopeSignature` to feed the scope-pass helper; do NOT rewrite the
    market bridge onto `ServedScopesProviderInterface`. The price override key today is the literal
    `"market:$market"` — ensure the mapped `ScopeSignature::toString()` yields exactly `market:$market`
    so `setOverride` keys are unchanged (single axis, no ksort reordering — they match). Document.
  - bulk upsert → keep `ProductPriceIndexRepository::upsertMany` (its `ON CONFLICT (product_id)` upsert
    is specific to the per-product entry shape and is NOT a delete+insert); optionally delegate its
    multi-row SQL assembly to the core bulk helper, but only if it preserves the `ON CONFLICT` upsert
    and the `?::jsonb` scopes casting. Do not force the price entry onto a delete+insert path.
- **Unified command — clean rename (decided).** DELETE `RebuildPriceIndexCommand`; the price index is
  now rebuilt through the core `index:rebuild price` command (task 004). In `catalog-price-index`'s
  module boot, register the price indexer in the core `IndexerRegistry` under the name `price`
  (`register('price', $priceIndexer)`). The old `catalog:price-index:rebuild` command is removed (no
  legacy alias — price-index is unreleased). Update/remove its command unit test accordingly (this is
  the ONE intentional public-surface change; everything else stays behavior-preserving).
- Keep `PriceIndexerInterface` and all INDEXING public signatures unchanged (downstream + the indexer
  tests depend on them) — only the CLI command surface changes.

## Requirements (Test Descriptions)
- [ ] `it still satisfies PriceIndexerInterface with reindexProducts reindexProduct and rebuildAll`
- [ ] `it produces the same base amount and per-market override entries after refactor`
- [ ] `it still performs a single bulk write per chunk`
- [ ] `it still restores the ambient market scope after reindex`

## Acceptance Criteria
- `PriceIndexer` consumes the shared core; its INDEXING public API (`PriceIndexerInterface`) unchanged.
- The existing `catalog-price-index` + `catalog-price-index-market` suites pass after the refactor —
  unchanged EXCEPT the removed `RebuildPriceIndexCommand` and its test (the one intentional surface
  change); the price indexer's behavior tests are the guard and must stay green.
- The price indexer is registered as `price` in the `IndexerRegistry` and rebuildable via `index:rebuild price`.

## Implementation Notes
(Left blank - filled in by programmer during implementation)
