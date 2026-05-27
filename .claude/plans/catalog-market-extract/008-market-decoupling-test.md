# Task 008: Add `MarketDecouplingTest` + extend `ComposerManifestTest`

**Status**: completed
**Depends on**: 007
**Retry count**: 0

## Description
Add the production safety net that prevents catalog from being re-coupled to market machinery in future PRs. Mirrors the existing `ScopeDecouplingTest` and `StorefrontDecouplingTest` patterns. Also extend `ComposerManifestTest` to assert catalog never grows a require entry pointing at any of the three new packages.

## Context
- Pattern reference: `packages/catalog/tests/Unit/ScopeDecouplingTest.php`, `packages/catalog/tests/Unit/StorefrontDecouplingTest.php`.
- The test walks every PHP file under `packages/catalog/src/` and asserts none contain any forbidden substring. It also walks `packages/catalog/tests/` and asserts the same (with `MarketDecouplingTest.php` itself excluded from the walk).
- Forbidden substrings:
  - `CategoryTreeMarketAssignment`
  - `assignTreeToMarket`
  - `resolveTreeForMarket`
  - `unassignMarket`
  - `TreeHasMarketAssignmentsException`
  - `Markommerce\\CatalogMarketCategoryTrees`
  - `Markommerce\\Market\\`
  - `Markommerce\\CatalogMarket\\`
- `ComposerManifestTest.php` already asserts the catalog require block contains expected entries. Extend it to also assert the block does NOT contain `markommerce/market`, `markommerce/catalog-market`, or `markommerce/catalog-market-category-trees` under either `require` or `require-dev`.

## Requirements (Test Descriptions)
- [x] `it skips its own file (basename MarketDecouplingTest.php) when walking the catalog source and test trees, mirroring ScopeDecouplingTest's self-exclusion at line 66`
- [x] `it finds no occurrences of CategoryTreeMarketAssignment in any catalog source or test file (excluding self)`
- [x] `it finds no occurrences of assignTreeToMarket, resolveTreeForMarket, or unassignMarket in any catalog source or test file (excluding self)`
- [x] `it finds no occurrences of TreeHasMarketAssignmentsException in any catalog source or test file (excluding self)`
- [x] `it finds no occurrences of the Markommerce\\CatalogMarketCategoryTrees namespace in any catalog source or test file (excluding self)`
- [x] `it finds no occurrences of the Markommerce\\Market or Markommerce\\CatalogMarket namespaces in any catalog source or test file (excluding self)`
- [x] `ComposerManifestTest asserts catalog.composer.json does not require markommerce/market in either require or require-dev`
- [x] `ComposerManifestTest asserts catalog.composer.json does not require markommerce/catalog-market in either require or require-dev`
- [x] `ComposerManifestTest asserts catalog.composer.json does not require markommerce/catalog-market-category-trees in either require or require-dev`

## Acceptance Criteria
- `packages/catalog/tests/Unit/MarketDecouplingTest.php` exists and all its requirements pass.
- `ComposerManifestTest.php` has the three new absence assertions.
- The full catalog test suite stays green.
- PHPStan + PHP-CS-Fixer clean.

## Implementation Notes

- Created `MarketDecouplingTest.php` with a shared `collectViolations()` helper function to walk both `src/` and `tests/` directories, excluding the test file itself from the walk.
- Extended `ComposerManifestTest.php` with three new absence assertions for `markommerce/market`, `markommerce/catalog-market`, and `markommerce/catalog-market-category-trees`.
- Also fixed `CatalogSeederTreeTest.php` which still contained a leftover `CategoryTreeMarketAssignmentRepository` import from task 007 — the class had been removed from catalog src but the integration test still referenced it. Removed the import and updated `makeSeederWithRealRepos()` and the idempotency test to use the updated `CategoryTreeService` constructor (which no longer takes `categoryTreeMarketAssignmentRepository`).
