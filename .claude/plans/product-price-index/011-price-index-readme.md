# Task 011: catalog-price-index — README

**Status**: completed
**Depends on**: 007, 008, 009
**Retry count**: 0

## Description
Write the `packages/catalog-price-index/README.md` following the project's Package README Standards, documenting what the index is, how to rebuild/reindex it, the N+1-free batch contract for contributors, the HasScopes storage shape, and the v1 freshness limitations. Runs last so it reflects what was actually built.

## Context
- Follow the README structure used by sibling packages (open `packages/catalog-market/README.md` or another package README for the exact section ordering/tone) and the standards in `.claude/code-standards.md` / `docs/DOCS-STANDARDS.md`.
- Cover:
  - **Purpose** — denormalized per-product (+ per-market) resolved price index for fast sorting/filtering; populated by the real pricing pipeline so it captures every contributor.
  - **Storage** — `ProductPriceIndexEntry` (HasScopes): one row per product, base `amount` + `currency_code`, per-market amounts in the `scopes` JSON. Table `catalog_product_price_index`.
  - **Rebuilding** — `catalog:price-index:rebuild [--chunk=N]`; programmatic `PriceIndexerInterface::reindexProduct(s)` / `rebuildAll()`.
  - **Markets** — base-only by default; install `markommerce/catalog-price-index-market` to add the `market` axis and index per-market amounts.
  - **Performance contract** — the indexer runs the batch pipeline once per market pass over a chunk; price-affecting contributors MUST load their data set-wise (no N+1). Point to `PriceContributorInterface`.
  - **v1 limitations** — no auto-invalidation/dirty-tracking/observer; rebuild after price, currency, market, or contributor changes. Sorting/consuming the index is a separate follow-up.
- Also add a brief note to the `markommerce/catalog-price-index-market` package (a short README or section) describing the bridge, if the README standard requires every package to have one.

## Requirements (Test Descriptions)
This is a documentation task — no automated tests. Acceptance is by checklist:

- [x] README exists at `packages/catalog-price-index/README.md` and follows the standard section layout
- [x] Documents the rebuild command and the reindex API
- [x] Documents the HasScopes storage shape and the market bridge
- [x] Documents the N+1-free contributor contract
- [x] Documents v1 freshness limitations

## Acceptance Criteria
- README matches the project's Package README Standards.
- All commands/symbols named in the README exist as built.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
