# Task 006: IndexedPriceSortOrder + boot registration (catalog-price-index)

**Status**: completed
**Depends on**: 001, 002, 003
**Retry count**: 0

## Description
Add the price sort orders to `catalog-price-index` and register them in that package's `module.php` boot. This is the proof of the extensibility goal: a separate package contributing `ORDER BY` options to the catalog category listing. Two keys, `price_asc` and `price_desc`, both LEFT JOIN the price index and push non-indexed (NULL-priced) products last.

## Context
- Related files:
  - New: `packages/catalog-price-index/src/Sorting/IndexedPriceSortOrder.php`
  - `packages/catalog-price-index/module.php` (add a `boot` closure that registers the price orders against the singleton `CategorySortOrderRegistry` from `catalog`; the package already requires `markommerce/catalog`). **Depends on task 003 having declared `CategorySortOrderRegistry` in catalog's `module.php` `singletons` — otherwise this boot would register into a different instance than the resolver/storefront read.** Confirm the registry is a shared singleton (mirrors how `PriceContributorRegistry` is wired) before relying on it here.
  - Index table `catalog_product_price_index` (`product_id`, `amount decimal(20,4) NULL`, `currency_code`, `scopes JSONB`) — see `packages/catalog-price-index/src/Entity/ProductPriceIndexEntry.php`
  - `prepareQuery()` should `leftJoin('catalog_product_price_index', 'catalog_products.id', '=', 'catalog_product_price_index.product_id')`. CONFIRMED signature: `leftJoin(string $table, string $first, string $operator, string $second)` (`marko/packages/database/src/Repository/RepositoryQueryBuilder.php:162`).
  - `sortFields()` returns one `SortField` over `catalog_product_price_index.amount` with the configured direction and `NullsPlacement::Last` (from task 001). The strategy expands that into the `(... IS NULL) ASC` companion + the real ordering (see task 001) — this task does NOT emit a literal `NULLS LAST` keyword (the builder cannot).
- `supportsKeyset()` returns `false` (the amount isn't a `Product` property; offset-only for v1).
- **Preference-replaceability (drives the class design — task 007 depends on this).** Marko `#[Preference]` swaps a *class/interface binding* resolved through the container; it does NOT intercept `new`. For task 007's `#[Preference(replaces: ...)]` to actually take effect, the boot closure MUST resolve the price order class(es) FROM THE CONTAINER (as closure-injected params, exactly like `catalog/module.php` injects `BasePriceContributor`), never via `new`. Because a single `#[Preference]` replaces one class with one class, you CANNOT have one parameterized `IndexedPriceSortOrder` constructed twice with different ctor args AND replace it per-direction. Therefore ship TWO concrete classes — `AscendingIndexedPriceSortOrder` and `DescendingIndexedPriceSortOrder` — each extending a shared abstract/base `IndexedPriceSortOrder` that holds the join + the direction-parameterized `sortFields()` logic. The boot injects both concrete classes and registers them. Task 007 then declares two `#[Preference]` overrides (one per direction). If you prefer a single class, document that the market layer (007) must then register-and-replace differently (e.g. re-register both keys in its own boot rather than via `#[Preference]`), but the two-class approach mirrors `ScopedIndexedMarketsProvider` most directly.

## Requirements (Test Descriptions)
- [x] `it exposes the price_asc and price_desc keys with human labels`
- [x] `it left joins the price index table when preparing the query`
- [x] `it sorts by the indexed amount column in the configured direction`
- [x] `it places non-indexed products last in ascending order`
- [x] `it places non-indexed products last in descending order`
- [x] `it reports that it does not support keyset pagination`
- [x] `it registers both price orders in the price-index module boot`

## Acceptance Criteria
- Price ordering works against the real `catalog_product_price_index` table in a feature test (products with and without index rows).
- NULL amounts are last in both directions.
- Registration happens in `catalog-price-index/module.php` boot; no change to the `catalog-price-index → catalog` dependency direction.
- PHPStan level 8 clean.

## Implementation Notes
- Abstract base `IndexedPriceSortOrder` holds `prepareQuery()` (LEFT JOIN), `supportsKeyset()=false`, and `sortFields()` (parameterized via abstract `direction()`).
- Two concrete classes: `AscendingIndexedPriceSortOrder` (key `price_asc`) and `DescendingIndexedPriceSortOrder` (key `price_desc`) extend the base.
- `module.php` boot injects both concrete classes from the container and registers them against the `CategorySortOrderRegistry` singleton.
- Integration tests in `packages/catalog-price-index/tests/Feature/Sorting/IndexedPriceSortOrderIntegrationTest.php` verify NULL-priced products appear last in both directions via the real `catalog_product_price_index` table.
- A local `PostgresTestConnection` helper was added at `packages/catalog-price-index/tests/Feature/Helpers/PostgresTestConnection.php`.
