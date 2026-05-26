# Task 010: Update FEATURES.md status table and tier rollups

**Status**: completed
**Depends on**: 003, 005, 006, 007, 008, 009
**Retry count**: 0

## Description
Flip the P3 row in the Refactor phases table from `pending` to `completed`. Add `markommerce/catalog-storefront-scope` to the Proposed new packages table under "Domain extensions" or a new sub-section "Storefront extensions". Update the Tier 2 / Tier 3 rollup tables and package counts so they reflect the new package being available for Tier 2 storefront merchants. Adjust the Tier 1 row's Desired column if needed (no change expected — `catalog-storefront` was already promised). Update the Side-by-side table to include a row for "Locale-aware storefront rendering" if it doesn't already capture this capability.

While editing FEATURES.md, also reconcile any stale status on the **P1** and **P2** rows of the Refactor phases table. Both phases have already merged on `develop` (P1 commit `282ec84`, P2 commit `8caa317`) but the file currently shows them as `pending`. Flip both to `completed` and fill in their `Plan / branch` cells (`scope-metadata-registry` and `catalog-scope-decouple` respectively). This is a pre-existing inconsistency surfaced by P3's edit; cleaning it up keeps the table truthful.

Tier 2 now splits cleanly into a **headless** flavor (Tier 1 + `scope` + `scope-pgsql` + `catalog-scope` + `locale` + `catalog-locale` + `config-scope` + `config-locale`) and a **storefront** flavor (headless + `catalog-storefront-scope`). The existing Tier 2 table groups both under one cell; this task introduces the distinction either inline (annotation in the Desired column) or as a separate Tier 2 row. Pick the lighter-weight option that keeps the table readable.

This is a documentation-only task; it has no test deliverables beyond a content-existence assertion test.

## Context
- File to modify: `FEATURES.md` (root).
- Sections to touch:
  - **Refactor phases table** (~line 188): change P3 row's Status from `pending` to `completed`. Set `Plan / branch` to `catalog-storefront-extract`. Update the `Outcome` cell if the actual scope differs from the original description (it does — the original P3 row mentions only `catalog-storefront`; this plan also created `catalog-storefront-scope`).
  - **Proposed new packages → Domain extensions table** (~line 137): the existing row for `catalog-storefront` should drop the `🆕` prefix (or stay if `🆕` is used to mean "this package was created during the P-numbered phases" — pick one convention). Add a new row for `catalog-storefront-scope` describing the Preference-based locale-aware grid rendering.
  - **Tier 2 multi-language shop section** (~line 80): mention that Tier 2 with a storefront also installs `catalog-storefront-scope`.
  - **Side-by-side table** (~line 110): no structural change; verify the capability columns still align.
  - **Package count, Desired state** (~line 122): Tier 2 was 14 packages; Tier 2 with storefront becomes 15. Document the storefront tier explicitly if the framing helps clarity.
- Reference: how P2 was marked completed (FEATURES.md currently shows P1 + P2 completed). Mirror that convention.

## Requirements (Test Descriptions)
- [x] `it marks the P3 row in the FEATURES.md Refactor phases table as completed`
- [x] `it sets the P3 row's Plan / branch column to catalog-storefront-extract`
- [x] `it flips the P1 row to completed and fills the Plan / branch with scope-metadata-registry (P1 already merged on develop)`
- [x] `it flips the P2 row to completed and fills the Plan / branch with catalog-scope-decouple (P2 already merged on develop)`
- [x] `it adds markommerce/catalog-storefront-scope to the Proposed new packages table with a description that mentions the Preference mechanism`
- [x] `it notes that Tier 2 storefront merchants additionally install markommerce/catalog-storefront-scope on top of the Tier 2 headless stack`
- [x] `it updates the package count rollup to reflect markommerce/catalog-storefront-scope being available for the Tier 2 storefront variant`
- [x] `it leaves the P4 and P5 rows untouched (still pending)`

## Acceptance Criteria
- All requirements have passing tests in a small `tests/Unit/Docs/FeaturesMdP3Test.php` (or similar).
- FEATURES.md reads consistently: every reference to "catalog-storefront" is grounded in the now-existing package, and `🆕` markers are no longer applied to it.
- Code follows project standards.

## Implementation Notes
- Created `tests/Unit/Docs/FeaturesMdP3Test.php` with 8 content-existence tests for FEATURES.md changes.
- Updated FEATURES.md Refactor phases table: P1 and P2 flipped to `completed` with correct branches (`scope-metadata-registry`, `catalog-scope-decouple`); P3 flipped to `completed` with branch `catalog-storefront-extract` and updated Outcome mentioning `catalog-storefront-scope`.
- Dropped `🆕` prefix from `catalog-storefront` and `catalog-scope` rows in Domain extensions table (these packages now exist). Added a new "Storefront extensions" sub-section for `catalog-storefront-scope` with a description referencing the Marko Preference mechanism.
- Tier 2 table now distinguishes headless and storefront flavors inline. Package count rollup updated: Tier 2 headless = 14, Tier 2 storefront = 15 (+ `catalog-storefront-scope`).
- Added "Locale-aware storefront rendering" row to Side-by-side capability table.
- P4 and P5 remain `pending` with `tbd` branches — untouched.
