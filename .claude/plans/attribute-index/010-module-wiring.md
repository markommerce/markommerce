# Task 010: Module wiring (catalog-attribute-index)

**Status**: pending
**Depends on**: 006, 007, 008, 009
**Retry count**: 0

## Description
Wire `catalog-attribute-index` as a Marko module: bind the indexer, repository, and reader; ensure
the index entity is provisioned. (The indexer's registration in the `IndexerRegistry` — name
`attribute` — and the unified `index:rebuild` command path are handled in task 009; there is NO
per-index command class.) Also confirm `markommerce/indexer` binds its shared services.

## Context
- Pattern: `packages/catalog-price-index/module.php` (bindings + boot) and
  `packages/catalog-price-index-market/module.php` (Preference + ScopedFieldRegistry).
- `catalog-attribute-index/module.php`:
  - `bindings`: `AttributeIndexerInterface` (if introduced) → `AttributeIndexer`;
    `ProductAttributeIndexRepository` (concrete or behind an interface); `IndexedAttributeReader`;
    `ServedScopesProviderInterface` → `CartesianServedScopesProvider` (or a domain-specific override).
    Bind only what the earlier tasks actually produced — do not invent interfaces.
  - `boot`: register the `AttributeIndexer` in the core `IndexerRegistry` under `attribute` (task 009).
    NO per-index command registration — the unified `index:rebuild` command lives in the core.
- `indexer/module.php`: register the `IndexerRegistry` as a singleton; register the unified
  `index:rebuild` command; bind core defaults (`ServedScopesProviderInterface` →
  `CartesianServedScopesProvider`, the `IndexRepository` base) where appropriate. Keep the kernel free
  of domain bindings.
- The `ProductAttributeIndexEntry` table provisions from entity metadata (standalone entity, not a
  companion — no `linkExtenders` needed). Verify it appears in the schema under the test harness.
- Do NOT add observers/auto-triggers (manual rebuild only this phase).

## Requirements (Test Descriptions)
- [ ] `it binds the attribute indexer in the container`
- [ ] `it binds the indexed attribute reader in the container`
- [ ] `it registers the indexer registry and unified index:rebuild command in the indexer module`
- [ ] `it provisions the catalog_product_attribute_index table from the entity`

## Acceptance Criteria
- Module boots without error; indexer/reader/repository bound; the `IndexerRegistry` + unified
  `index:rebuild` command are available; no per-index command class.
- No observers/auto-triggers wired (manual rebuild only).

## Implementation Notes
(Left blank - filled in by programmer during implementation)
