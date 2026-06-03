# Task 015: Update FEATURES.md — flip P5 status, refresh tier counts, register new packages

**Status**: completed
**Depends on**: 013, 014
**Retry count**: 0

## Description
Update `FEATURES.md` to reflect Phase 5 completion. Flip the P5 row's status from `pending` to `completed` and set its plan field from `tbd` to `config-scope-decouple`. Update the Tier 2 / Tier 3 desired-state package lists to include `config-scope-pgsql` (which P5 introduced beyond the original three FEATURES.md predicted). Refresh the package-count summary lines. Update the Tier 1 row to note that `config-pgsql` no longer emits an `overrides` column. Update the "Proposed new packages" table: flip the four new packages from `🆕` (planned) to "shipped".

## Context
- Related files:
  - `FEATURES.md` (root)
- Patterns to follow: P4's `013-update-features-md.md` (and the prior P4 commit that flipped row status) is the template.
- Locked-in deltas for this task:
  - P5 row: `pending` → `completed`; `tbd` → `config-scope-decouple`; the right-most "Outcome" cell stays accurate.
  - Tier 2 desired-state row: add `🆕 config-scope-pgsql` after `🆕 config-scope` in the headless list.
  - Tier 2 storefront row: same — the additional package propagates.
  - Tier 3 row: same — propagates.
  - Tier 2 headless count: 14 → 15.
  - Tier 2 storefront count: 15 → 16.
  - Tier 3 count: 17 → 18.
  - Tier 1 row "Current"/"Desired" cells: clarify that `config-pgsql` no longer emits the `overrides` JSONB column.
  - "Proposed new packages" → "Axis × domain bridges" rows for `config-scope`, `config-locale`, `config-market`: drop `🆕` prefix.
  - "Proposed new packages" → "Domain extensions" section: drop `🆕` from `config-scope` if it appears there; add `markommerce/config-scope-pgsql` row pointing at the Postgres driver.

## Requirements (Test Descriptions)
- [ ] `it flips the P5 row's status from pending to completed in FEATURES.md`
- [ ] `it sets the P5 row's plan field to config-scope-decouple in FEATURES.md`
- [ ] `it includes config-scope-pgsql in the Tier 2 headless desired-state package list in FEATURES.md`
- [ ] `it updates the Tier 2 headless package count to 15 in FEATURES.md`
- [ ] `it updates the Tier 2 storefront package count to 16 in FEATURES.md`
- [ ] `it updates the Tier 3 package count to 18 in FEATURES.md`
- [ ] `it removes the 🆕 marker from config-scope, config-locale, and config-market in the Proposed new packages tables`
- [ ] `it adds a row for markommerce/config-scope-pgsql in the Proposed new packages tables (or marks the package shipped)`
- [ ] `it notes that config-pgsql no longer emits an overrides JSONB column in the Tier 1 row`

## Acceptance Criteria
- All requirements have passing tests (a single test file under `tests/Unit/Features/FeaturesMdP5Test.php` or similar that reads `FEATURES.md` from the project root and asserts the cell contents).
- Markdown linting (if configured) passes on the updated file.
- The narrative voice and table formatting of FEATURES.md stay consistent with the surrounding content.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
