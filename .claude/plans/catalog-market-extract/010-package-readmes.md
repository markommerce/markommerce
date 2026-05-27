# Task 010: Package READMEs

**Status**: completed
**Depends on**: 002, 003, 005, 006, 007
**Retry count**: 0

## Description
Write READMEs for the three new packages and trim the catalog README. All four READMEs follow the project's package README standards (see `docs/DOCS-STANDARDS.md`). The `catalog-market` README explicitly calls out the placeholder status so merchants who install it don't assume it's broken.

## Context
- README templates to mirror:
  - `packages/locale/README.md` — for `packages/market/README.md`
  - `packages/catalog-locale/README.md` — for `packages/catalog-market/README.md`
  - `packages/catalog-scope/README.md` — for `packages/catalog-market-category-trees/README.md` (structurally similar: a bridge that adds capabilities to catalog)
- `catalog-market/README.md` must include a "Placeholder status" section that:
  - States the package ships with an empty boot closure today.
  - Explains that `Product` does not yet have `price`/`visibility` columns, so no fields are registered.
  - Points to FEATURES.md's tier 3 row for the planned end state.
- `catalog-market-category-trees/README.md` covers:
  - Installation and required peer packages.
  - Quick example using `CategoryTreeMarketAssignmentService::assignTreeToMarket`, then `CategoryTreeMarketResolver::resolveTreeForMarket`.
  - A note that installing the package activates the `CategoryTreeServiceDeletePlugin` automatically, replacing the friendly `TreeHasMarketAssignmentsException` guard that catalog had before P4.
- catalog README edits land here (the catalog-side mention of market needs to cross-link to the new package now that it exists):
  - Strip "per-market category trees" from the opening blurb (already done in task 007 — re-verify).
  - Add a "Market-aware multi-tree" section (one paragraph) pointing to `markommerce/catalog-market-category-trees`.
- Each new package gets a `tests/ReadmeTest.php` mirroring the existing test pattern.
- `packages/catalog/tests/Unit/ReadmeTest.php` is updated to drop the `resolveTreeForMarket` literal assertion (already done in task 007 — re-verify) and add a literal assertion for the new cross-link.

## Requirements (Test Descriptions)
- [x] `markommerce/market README declares installation, quick example, and documentation link sections per the project standard`
- [x] `markommerce/catalog-market README declares its placeholder status with a Placeholder status section`
- [x] `markommerce/catalog-market README cross-links to FEATURES.md for the planned end state`
- [x] `markommerce/catalog-market-category-trees README documents installation, the resolver, the assignment service, and the deleteTree plugin guard`
- [x] `markommerce/catalog README cross-links to markommerce/catalog-market-category-trees in a Market-aware multi-tree section`
- [x] `each new package ships a ReadmeTest asserting the README contains its required sections`
- [x] `the updated catalog ReadmeTest asserts the new cross-link to markommerce/catalog-market-category-trees`

## Acceptance Criteria
- All four READMEs exist with the required sections.
- Three new `ReadmeTest.php` files exist and pass.
- catalog's updated `ReadmeTest.php` passes.
- READMEs comply with `docs/DOCS-STANDARDS.md`.

## Implementation Notes

- All market and catalog-market READMEs already existed from tasks 001/002; added the required new sections and tests.
- Added `## Placeholder Status` section to `packages/catalog-market/README.md` explaining the empty boot closure and linking to FEATURES.md tier 3 row.
- Rewrote `packages/catalog-market-category-trees/README.md` to fully document installation, resolver, assignment service, and the deleteTree plugin guard.
- Created `packages/catalog-market-category-trees/tests/Unit/ReadmeTest.php` (new file).
- Updated `packages/market/tests/ReadmeTest.php`, `packages/catalog-market/tests/ReadmeTest.php`, and `packages/catalog/tests/Unit/ReadmeTest.php` with new test cases matching the requirement descriptions.
- The catalog README already had the `catalog-market-category-trees` cross-link from task 007 work.
