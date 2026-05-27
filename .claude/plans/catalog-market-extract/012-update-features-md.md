# Task 012: Update `FEATURES.md`

**Status**: completed
**Depends on**: 007, 008, 009, 010, 011

**Retry count**: 0

## Description
Update `FEATURES.md` to reflect P4's completion. P4 row status flips from `pending` to `completed`; plan field flips from `tbd` to `catalog-market-extract`. Refresh Tier 3 package count if needed (should still be 18: Tier 2 storefront + `market` + `catalog-market` + `catalog-market-category-trees`). Add a brief note under "Open questions" or the P4 row that `catalog-market` ships as a no-op placeholder pending `Product.price`/`Product.visibility` columns.

## Context
- Current P4 row in `FEATURES.md`:
  ```
  | **P4** | Create `market`, `catalog-market`; extract `catalog-market-category-trees` | `pending` | tbd | Tier 3 reachable. ... |
  ```
  Update to:
  ```
  | **P4** | Create `market`, `catalog-market`; extract `catalog-market-category-trees` | `completed` | `catalog-market-extract` | Tier 3 reachable. ... |
  ```
- Verify the Tier 3 package count line (currently "18 packages") matches: Tier 2 storefront (15) + `market` + `catalog-market` + `catalog-market-category-trees` = 18. ✓
- Verify the Tier 3 row in the "Side-by-side: what each merchant installs" table — no shape change expected since the capabilities are unchanged.
- Add a single bullet to "Refactor phases" → "Decisions locked in" or to the P4 row's outcome cell noting that `catalog-market` ships empty today, pending `Product.price`/`Product.visibility`. This decision was made during P4 clarification (see plan's Discovery Notes).

## Requirements (Test Descriptions)
- [x] `FEATURES.md P4 row reads completed for status`
- [x] `FEATURES.md P4 row plan / branch field reads catalog-market-extract`
- [x] `FEATURES.md still claims 18 packages for Tier 3 and the math checks out`
- [x] `FEATURES.md documents that catalog-market ships as a no-op placeholder pending Product.price and Product.visibility`

## Acceptance Criteria
- `FEATURES.md` edited as described.
- No other rows changed (P1, P2, P3 remain `completed`; P5 remains `pending`).
- The `markdown-link-check` or equivalent docs CI step passes.

## Implementation Notes

- P4 row status changed from `pending` to `completed`, plan/branch from `tbd` to `` `catalog-market-extract` ``.
- Tier 3 count remains 18 (Tier 2 storefront 15 + `market` + `catalog-market` + `catalog-market-category-trees`); no change needed.
- No-op placeholder note added inline to the P4 row's Outcome cell: `catalog-market` ships today without `Product.price`/`Product.visibility` registrations; those are deferred until those columns exist on `Product`.
- P1, P2, P3 remain `completed`; P5 remains `pending`. No other rows changed.
