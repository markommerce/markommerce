# Task 012: READMEs (indexer + catalog-attribute-index) + docs

**Status**: pending
**Depends on**: 001, 002, 003, 004, 005, 006, 007, 008, 009, 010, 011
**Retry count**: 0

## Description
Write the `markommerce/indexer` and `markommerce/catalog-attribute-index` READMEs per the Package
README Standard, reflecting only the shipped API.

## Context
- Standard: `.claude/package-standard.md` + Package README Standards in `.claude/code-standards.md`.
  Mirror `packages/catalog-price-index/README.md` for tone/length.
- `indexer/README.md`: purpose (shared indexer kernel reused by price + attribute indexes);
  `IndexerInterface` + `AbstractIndexer` (chunked rebuild/reindex), `ServedScopesProviderInterface` +
  cartesian provider (bounded), the scope-pass helper, the `IndexRepository` base, the
  `IndexerRegistry` (name → indexer), and the unified `index:rebuild [name?] --chunk` command
  (rebuild one or all registered indexes; Magento-`indexer:reindex`-style). Note it abstracts the
  indexer LIFECYCLE/utilities, not the row shape. List its two registered consumers (`price`,
  `attribute`). PHP 8.5 / no-final / strict-types.
- `catalog-attribute-index/README.md`: purpose (resolved EAV read-model for layered-nav
  filtering/faceting); the `ProductAttributeIndexEntry` shape (one resolved row per
  product×code×signature, typed columns); `AttributeIndexer` (filterable/facetable, per-served-scope,
  multiselect exploded); the `IndexedAttributeReader` live fallback (correct reads even when stale);
  rebuilt via the unified `index:rebuild attribute` command (registered as `attribute`; no
  auto-invalidation this phase). Out of scope: faceting query API / layered-nav UI (Phase 5), search
  (Phase 6), observers/async.

## Requirements (Test Descriptions)
- [ ] `it documents the shared indexer core purpose and lifecycle in its README`
- [ ] `it documents the attribute index EAV shape rebuild command and live fallback in its README`

> Note: documentation-completeness checks — verify the READMEs exist and contain the required
> sections (file-content assertion per the project's README-task convention).

## Acceptance Criteria
- Both READMEs follow the standard sections/length; content matches the shipped API (no unbuilt features).

## Implementation Notes
(Left blank - filled in by programmer during implementation)
