# Task 007: Market-scoped price sort override (catalog-price-index-market)

**Status**: completed
**Depends on**: 001, 006
**Retry count**: 0

## Description
Layer market-aware price ordering into `catalog-price-index-market` via a `#[Preference]` override of the indexed price sort order. When this package is installed, price ordering uses the active market's JSON override (`scopes->'market:xx'->>'amount'`) falling back to the base `amount`; without it, base-amount ordering from task 006 stands.

## Context
- Related files:
  - New: `packages/catalog-price-index-market/src/Sorting/ScopedAscendingIndexedPriceSortOrder.php` and `ScopedDescendingIndexedPriceSortOrder.php` (mirroring task 006's two concrete classes; a shared base holding the COALESCE-expression logic is fine).
  - Pattern to copy: `packages/catalog-price-index-market/src/ScopedIndexedMarketsProvider.php` (`#[Preference(replaces: ...)]`, injects `ScopeRegistryInterface`)
  - The `replaces:` targets are the TWO concrete classes from task 006 (`AscendingIndexedPriceSortOrder`, `DescendingIndexedPriceSortOrder`) — one `#[Preference]` per direction. This requires task 006's boot to resolve those classes through the container (already mandated in 006); a `#[Preference]` cannot replace an instance created via `new`.
  - Active market path comes from the scope registry/resolver (`market:xx` form) — derive it the same way existing market code does (see `ScopedIndexedMarketsProvider::markets()` using `ScopeRegistryInterface::getAxis('market')` / `getHierarchy('market')`); never hardcode the `market:xx` literal.
  - `sortFields()` returns a `SortField` with a RAW expression (task 001): `COALESCE((catalog_product_price_index.scopes->'market:xx'->>'amount')::numeric, catalog_product_price_index.amount)` with direction configured and `NullsPlacement::Last`. **The `->>'amount'` text accessor yields TEXT, so cast to `::numeric` (or `::decimal`) or string ordering will sort `"100" < "9"`** — this is a real correctness bug, not cosmetic. The strategy expands `NullsPlacement::Last` into a companion `(<that COALESCE expr>) IS NULL ASC` clause (per task 001) — there is no literal `NULLS LAST`. Verify the full expression (parentheses, `::numeric`, `->>`) clears `IdentifierValidator::assertNoDangerousPatterns` (it does: only `;`, `--`, `/* */`, backtick are denied).
  - `prepareQuery()` keeps the same LEFT JOIN as the base order.
  - Index JSON shape: `{"market:us":{"amount":"12.3400"}}` (see `PriceIndexer` / `ProductPriceIndexRepository::upsertMany`). The `scopes` property is registered as market-scoped in this package's existing `module.php` boot (`ScopedFieldRegistry`).

## Requirements (Test Descriptions)
- [x] `it replaces both base indexed price sort orders via preferences` (one per direction)
- [x] `it casts the json override amount to numeric so 100 sorts after 9` (guard the text-vs-numeric ordering bug)
- [x] `it orders by the active market override amount when present`
- [x] `it falls back to the base amount when the active market has no override`
- [x] `it still places products with no price last`
- [x] `it keeps the price index left join in the prepared query`
- [x] `it falls back to base-amount ordering when no market axis is configured`

## Acceptance Criteria
- A feature test seeds index rows with per-market JSON overrides and asserts ordering differs by active market.
- The raw COALESCE expression uses only code-defined constants and scope-registry-derived market identifiers (no request input) — document this injection-safety invariant.
- Dependency direction unchanged; PHPStan level 8 clean.

## Implementation Notes

- Added `ScopedIndexedPriceSortOrder` (abstract base) in `packages/catalog-price-index-market/src/Sorting/` injecting `ScopeRegistryInterface` + `ScopeContext`.
- `ScopedAscendingIndexedPriceSortOrder` and `ScopedDescendingIndexedPriceSortOrder` extend the base with `#[Preference(replaces: ...)]` attributes targeting the task-006 concrete classes.
- `sortFields()` returns a `SortField` with a COALESCE expression (`::numeric` cast) when an active market scope is present, or a plain base-amount `SortField` when no market is configured or no non-default scope is active.
- Injection-safety comment in the base class documents that the COALESCE expression is built from code-defined constants and registry-derived identifiers only.
- 7 unit tests in `tests/Unit/Sorting/ScopedIndexedPriceSortOrderTest.php` and 5 DB integration tests in `tests/Feature/Sorting/ScopedIndexedPriceSortOrderIntegrationTest.php` (tagged `integration-destructive`).
- PHPStan level 8 and phpcs clean.
