# Task 011: Invariant-matrix + profile-specific demonstration tests

**Status**: completed
**Depends on**: 009, 010
**Retry count**: 0

## Description
Prove the framework end-to-end with real tests: (a) invariant-matrix tests that run the SAME behavior across all three profiles via the `storeProfiles` dataset, asserting topology-independent invariants; and (b) profile-specific tests asserting topology-dependent behavior (e.g. per-market price override only exists in the two-market profile). These both validate the harness and serve as the reference example for developers.

## Context
- Use the category sort-ordering / pricing feature already in the codebase as the subject (it spans catalog + price-index + price-index-market, perfect for a modularity matrix). Resolve `CategoryAssignmentService` + the sort orders + `ProductPriceIndexRepository` from the booted store; use the factories (task 010) to seed.
- Place in `packages/testing/tests/Feature/` (framework self-demonstration) and/or a catalog-level integration test — pick the location that reads as the canonical example; document it in the README (task 015).
- Invariant examples (run via `->with('storeProfiles')`):
  - A category lists its assigned products in position order by default — must hold in ALL profiles.
  - Sorting by indexed price orders ascending — holds wherever the price-index module is present; in the simple profile (no price-index) the `price_asc` sort order is NOT registered, so assert it's absent rather than asserting ordering. (Shows graceful degradation — the invariant is "the page renders + position sort always works"; price sort is profile-conditional.)
- Profile-specific examples:
  - two-markets profile: an indexed price with a per-market JSON override sorts/render differently in `inScope(market:'eu')` vs `inScope(market:'us')`.
  - simple profile: requesting `?sort=price_asc`-equivalent behavior is unavailable (the order isn't registered) — assert the registry does not contain it.
- Keep assertions behavioral (through services/registries), not raw SQL.

## Requirements (Test Descriptions)
- [x] `it lists products in position order by default in every profile` (matrix; group integration-destructive)
- [x] `it renders a category listing without error in every profile` (matrix; group integration-destructive)
- [x] `it registers the indexed price sort orders only when the price-index module is present` (matrix asserting presence/absence per profile; group integration-destructive)
- [x] `it orders by indexed price ascending in profiles that include the price index` (group integration-destructive)
- [x] `it reflects the active market price override in the two-markets profile` (group integration-destructive)
- [x] `it does not expose price sort orders in the simple profile` (group integration-destructive)

## Acceptance Criteria
- A single dataset-driven test body runs across all three profiles with profile-appropriate assertions.
- Profile-specific behavior (market override; absence of price sort in simple) is proven.
- Tests are clean, factory-driven, and serve as the canonical usage example.
- PHPStan level 8 clean (run with `php -d memory_limit=2G`).

## Implementation Notes

Test file: `packages/testing/tests/Feature/InvariantMatrixTest.php`

- Three matrix tests drive all six assertions (3 profiles × 3 invariants) via `->with('storeProfiles')`.
- The `storeProfiles` dataset is defined at file scope (scoped to `Feature/` directory), which is separate from the `Integration/`-scoped dataset in `IntegrationTestCaseTest.php` — Pest 4 dataset scoping prevents collision.
- Profile helpers (`matrixSimpleProfile`, `matrixTwoLocalesProfile`, `matrixTwoMarketsProfile`) extend the preset configurations to include `marko/database-pgsql` so the container can auto-wire repository dependencies (the presets omit pgsql for the non-simple profiles).
- Three profile-specific tests cover: indexed-price ascending ordering (two-markets), market-scoped price override (EU vs US), and absence of price sorts in simple.
- Market-scoped overrides set via `ProductPriceIndexEntry::setOverride('market:us', 'amount', ...)` and persisted via `upsertMany()`.
- PHPStan level 8 clean.
