# Task 012: catalog-storefront — use indexed price on category page

**Status**: complete
**Depends on**: 009
**Retry count**: 0

## Description

Replace the per-product `PriceResolver::resolve()` call in `ProductGridComponent` with a single batch lookup against `ProductPriceIndexRepositoryInterface`. The index was built by T008/T009 — this task is the consuming side. The result: one query for all prices on the page instead of N calls through the full contributor pipeline.

## Context

### Current behaviour (N+1)

`ProductGridComponent::data()` loops over the page's products and calls `PriceResolver->resolve(PriceContext::forProduct($product))` per product. That invokes `BatchPriceResolver` → all `PriceContributorInterface` contributors → for a page of 20 products that is 20 independent pipeline executions.

### Target behaviour (batch, O(1) queries)

1. Collect `$productIds` from the page result.
2. Call `ProductPriceIndexRepositoryInterface::findByProductIds($productIds)` — one `SELECT … WHERE product_id IN (…)` query returning `array<int, ProductPriceIndexEntry>` keyed by `productId`.
3. In the loop, read `$entries[$product->id]->amount` + `$entries[$product->id]->currencyCode` to build the `Money` object directly via `Money::of($entry->amount, $currencyResolver->base())`.
4. Fall back to `PriceResolver->resolve()` for products missing from the index (not yet indexed), then catch `PriceUnavailableException` as before.

### Changes required

**`ProductPriceIndexRepositoryInterface`** — add one method:
```php
/**
 * @param list<int> $productIds
 * @return array<int, ProductPriceIndexEntry> keyed by productId
 */
public function findByProductIds(array $productIds): array;
```

**`ProductPriceIndexRepository`** — implement via `query()->whereIn('product_id', $productIds)->getEntities()`, build and return the map.

**`ProductGridComponent`** (in `catalog-storefront`) — inject `ProductPriceIndexRepositoryInterface` + `CurrencyResolver`; replace the per-product `priceResolver->resolve()` call with the batch path described above. Keep the `PriceResolver` injection for the fallback. Add `markommerce/catalog-price-index` to `catalog-storefront/composer.json`.

**No new package needed** — `catalog-storefront` is the natural consumer and can directly depend on `catalog-price-index`.

## Requirements (Test Descriptions)

- [x] `it adds findByProductIds to the repository interface and implementation`
- [x] `it loads all page prices in a single index query`
- [x] `it falls back to PriceResolver for products absent from the index`
- [x] `it returns no formatted price when neither the index nor PriceResolver can resolve`

## Acceptance Criteria

- `ProductGridComponent` on a 20-product page issues exactly 1 price query (via `findByProductIds`), not 20.
- Products missing from the index still display a price (fallback path).
- PHPStan level 8 clean; phpcs clean.

## Implementation Notes

- Added `findByProductIds(array $productIds): array` to `ProductPriceIndexRepositoryInterface` and implemented in `ProductPriceIndexRepository` using a raw SQL `WHERE product_id IN (...)` query, building a `array<int, ProductPriceIndexEntry>` map keyed by `productId`.
- `ProductGridComponent` now accepts two new constructor params: `ProductPriceIndexRepositoryInterface` and `CurrencyResolver`. Before the per-product loop, it calls `findByProductIds()` once, then in the loop uses the index entry if present (falling back to `PriceResolver` for missing entries, then to null on `PriceUnavailableException`).
- `ScopedProductGridComponent` updated to pass the two new params through to `parent::__construct()`.
- Added `markommerce/catalog-price-index` and `markommerce/currency` to `catalog-storefront/composer.json`.
- All feature and scope tests updated with in-test empty-index fake implementations for the new dependencies.
- PHPStan level 8 clean; phpcs clean on modified files.
