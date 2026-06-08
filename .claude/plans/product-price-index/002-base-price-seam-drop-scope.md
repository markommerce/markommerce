# Task 002: Base-price seam in catalog + drop the scope dependency

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Introduce a `ProductBasePriceProviderInterface` seam so the catalog pricing pipeline no longer imports `scope`. Catalog ships a `RawProductBasePriceProvider` that reads `$product->priceAmount` directly (market-agnostic). Refactor `PriceResolver` to obtain its base amount from the provider instead of `ScopeResolver`, then remove `markommerce/scope` and `markommerce/catalog-scope` from catalog's composer. The market-aware behavior is restored by the scoped provider in T003 — so the scope-specific `PriceResolver` tests MOVE to catalog-market in T003 and are removed from catalog here.

## Context
- The provider must be **batch-capable** (the indexer reuses it) and tolerate **transient products with no id** (the single-resolve path passes a bare `Product`). So key by the CALLER'S keys, not the DB id.
- Interface (`packages/catalog/src/Pricing/Contracts/ProductBasePriceProviderInterface.php`):
  ```php
  interface ProductBasePriceProviderInterface
  {
      /**
       * @param array<array-key, Product> $products  caller-keyed map
       * @return array<array-key, ?string>           same keys → raw decimal amount or null
       */
      public function amountsFor(array $products): array;
  }
  ```
- `RawProductBasePriceProvider` returns `array_map(fn (Product $p) => $p->priceAmount, $products)` (preserving keys). No scope, no queries.
- Refactor `PriceResolver`: drop `ScopeResolver`/`ScopeContext` constructor deps and all `scopeContext->in/clear/get('market')` logic. New deps: `ProductBasePriceProviderInterface` + `CurrencyResolver`. `resolve()` calls `$this->basePriceProvider->amountsFor([$context->product])[0]`, and if null throws `PriceUnavailableException::forContext($context)`, else `Money::of($amount, $this->currencyResolver->base())`. **Market becomes ambient** — `PriceResolver` no longer switches the scope; the active market is whatever the caller already set on `ScopeContext`, which only the scoped provider (T003) reads. (Drop the `$context->market` scope-switching; the field stays on `PriceContext` for now but the base pipeline ignores it.)
- `RawProductBasePriceProvider::amountsFor` uses `array_map(fn (Product $p) => $p->priceAmount, $products)` — `array_map` with a single array PRESERVES keys (associative and non-sequential), so caller keys round-trip. (Note: `array_map` only preserves keys with ONE input array; never pass a second array here.)
- Register the binding in `packages/catalog/module.php`: add `ProductBasePriceProviderInterface::class => RawProductBasePriceProvider::class` to the EXISTING `bindings` array (catalog's `module.php` currently returns ONLY a `bindings` key — keep `PriceResolverInterface::class => PriceResolver::class` from T001 there too; T005/T006 will later add `singletons` and a `boot` closure to this same file).
- **Behavior preservation for the storefront (ambient market):** `ProductGridComponent`/`ProductCard` call `PriceResolver::resolve(PriceContext::forProduct($product))` with NO market — they rely on whatever market is already active on the ambient `ScopeContext` (set by the request/market middleware). Before this plan, `PriceResolver` only switched scope when `$context->market !== null`, which the storefront never set — so resolution ALREADY used the ambient context's companion walk. After this change the raw provider returns the bare `priceAmount` (no scope), and the SCOPED provider (T003, installed with catalog-market) restores the ambient-market companion walk. Net effect for a storefront WITH catalog-market installed is identical; WITHOUT it, prices are raw (which is the intended scope-free behavior). Confirm the storefront tests (which inject fake `PriceResolverInterface`s) are unaffected — they don't touch the provider.
- **composer.json:** remove `markommerce/scope` and `markommerce/catalog-scope` from `packages/catalog/composer.json` `require` (added temporarily in T001). Confirm nothing left in `catalog/src` imports `Markommerce\Scope\…` or `Markommerce\CatalogScope\…` (grep).
- The scope-dependent `PriceResolver` tests (`resolves the per market price amount…`, `uses the per market currency override…`, `restores the previous market scope…`) MUST be deleted from catalog here (they move to T003). Keep the raw + null + binding + currency tests.

## Requirements (Test Descriptions)
- [x] `it returns the raw price amount for each product preserving caller keys`
- [x] `it returns null for a product with no price amount`
- [x] `it does not query scope storage to resolve a base amount`
- [x] `it resolves a product price into money using the base currency`
- [x] `it throws PriceUnavailableException when the product has no price amount`
- [x] `it binds the raw base price provider to the base price provider interface`
- [x] `it binds the base resolver to the price resolver interface`
- [x] `it resolves a price without any scope module installed`

## Acceptance Criteria
- `packages/catalog/composer.json` does NOT require `markommerce/scope` or `markommerce/catalog-scope`.
- No file under `packages/catalog/src/` imports a `Markommerce\Scope` / `Markommerce\CatalogScope` symbol.
- `PriceResolver` constructor takes `ProductBasePriceProviderInterface` + `CurrencyResolver` only.
- PHPStan level 8 clean; phpcs clean.

## Implementation Notes
- Created `ProductBasePriceProviderInterface` at `packages/catalog/src/Pricing/Contracts/ProductBasePriceProviderInterface.php`
- Created `RawProductBasePriceProvider` at `packages/catalog/src/Pricing/RawProductBasePriceProvider.php` — uses `array_map` preserving caller keys
- Refactored `PriceResolver` constructor to take `ProductBasePriceProviderInterface` + `CurrencyResolver` only (dropped `ScopeResolver`/`ScopeContext`)
- Added `ProductBasePriceProviderInterface::class => RawProductBasePriceProvider::class` binding to `module.php`
- Removed `markommerce/scope` from `packages/catalog/composer.json`
- Rewrote `packages/catalog/tests/Feature/Pricing/PriceResolverTest.php` — removed the 3 scope-dependent tests, kept raw/null/binding tests
- Added new unit tests in `packages/catalog/tests/Unit/Pricing/RawProductBasePriceProviderTest.php` and `PriceResolverTest.php`
- Updated `ModuleBindingsTest.php` with binding verification tests for requirements 6 and 7
- Updated `ScopeDecouplingTest.php` to remove temporary Pricing/ scope exception
- The 5 pre-existing failures in `CatalogSeederTreeTest` (PDOException: price_amount column missing) are unrelated to this task
