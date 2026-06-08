# Task 003: catalog-market — scoped base-price provider (`#[Preference]`)

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Restore market-aware base pricing by adding a `ScopedProductBasePriceProvider` to `catalog-market` that overrides the raw provider via `#[Preference]`. It reads each product's `priceAmount` through `ScopeResolver` (honouring the ambient market on `ScopeContext` and the product's `ProductScopedOverrides` companion), falling back to the raw amount. The scope-specific `PriceResolver` tests removed from catalog in T002 move here, proving the full market-resolved pipeline works exactly as before when `catalog-market` is installed.

## Context
- New file `packages/catalog-market/src/Pricing/ScopedProductBasePriceProvider.php`:
  ```php
  #[Preference(replaces: RawProductBasePriceProvider::class)]
  class ScopedProductBasePriceProvider implements ProductBasePriceProviderInterface
  {
      public function __construct(private ScopeResolver $scopeResolver) {}

      public function amountsFor(array $products): array
      {
          return array_map(
              fn (Product $p) => $this->scopeResolver->resolved($p, 'priceAmount'),
              $products,
          ); // preserves keys; resolved() reads the ambient ScopeContext market + companion, else raw
      }
  }
  ```
  Note `array_map` over an assoc array preserves keys. `resolved()` returns `mixed` — the `@return array<array-key,?string>` is satisfied because stored amounts are decimal strings or null; add a cast/`@var` as needed for PHPStan.
- catalog-market already registers `Product.priceAmount` on the `market` axis (its `boot`), so `resolved($p,'priceAmount')` walks the market override. No change to that registration.
- composer: `catalog-market` already requires `catalog`, `catalog-scope`, `market`. The `#[Preference]` is auto-discovered (attribute scan) — no manual binding needed, mirror how other `#[Preference]` classes register. Confirm `ScopeResolver` is constructor-injectable here.
- **Move the scoped tests** from catalog (where T001 parked them) into `packages/catalog-market/tests/Feature/`: `resolves the per market price amount…`, `uses the per market currency override…`, `restores the previous market scope on the shared ScopeContext after resolving`. The last one now asserts the AMBIENT market is untouched by resolution (the resolver no longer switches it) — rewrite it to set `ScopeContext` to `us`, resolve, and assert it is still `us` (the provider reads, never mutates, the context).
- **Build the `PriceResolver` under test correctly for the T003 point in time.** After T002, `PriceResolver`'s constructor is `(ProductBasePriceProviderInterface $basePriceProvider, CurrencyResolver $currencyResolver)` — it NO LONGER takes `ScopeResolver`/`ScopeContext`. So these moved tests must build `new PriceResolver(basePriceProvider: new ScopedProductBasePriceProvider($scopeResolver), currencyResolver: …)`. The previous market-scope-switching logic is gone; the market must be set as the AMBIENT `ScopeContext` BEFORE calling `resolve()` (the test sets `$scopeContext->in('market','us')` itself, since `PriceContext::forProduct($product, 'us')` no longer drives scope switching). The `$context->market` field is now inert for the base pipeline.
- **Fix the relative `require dirname(...)` paths again.** These tests move from `packages/catalog/tests/Feature/Pricing/` (depth set in T001) to `packages/catalog-market/tests/Feature/`. Recompute every `dirname(__DIR__, N)` so sibling-package `module.php` requires (`/scope/…`, `/market/…`, `/currency-market/…`, `/catalog-market/…`) resolve to `packages/<pkg>/module.php`. From `packages/catalog-market/tests/Feature/`, `dirname(__DIR__, 3)` is `packages/`.
- **Boot order for the `#[Preference]`:** these tests must boot catalog-market's module so the `ScopedProductBasePriceProvider` `#[Preference]` is registered AND so `ScopedFieldRegistry->register(Product, 'priceAmount', ['market'])` (catalog-market's existing `boot`) runs — otherwise `ScopeResolver->resolved($p,'priceAmount')` won't walk the market override. Mirror the boot-loop helper from the original `PriceResolverTest`.
- **CRITICAL — make these tests survive T006's `PriceResolver` constructor change.** T006 rewrites `PriceResolver` to take `BatchPriceResolverInterface` (dropping the direct `ProductBasePriceProviderInterface`/`CurrencyResolver` ctor). If these moved tests hand-construct `new PriceResolver(basePriceProvider: …, currencyResolver: …)`, they will COMPILE-break the moment T006 merges. To avoid mid-plan rework, prefer one of:
  1. **Resolve `PriceResolverInterface` from a booted container** (the container wires whatever ctor `PriceResolver` currently has) and assert on `resolve()` output — constructor-agnostic. This is the recommended approach; it tests the real wired pipeline (scoped provider behind the contributor) and keeps passing across T006.
  2. If a unit-level construction is truly needed at T003, isolate it so T006 updates exactly that construction site. Note in the task's Implementation Notes that T006 must re-verify these tests.
  The provider-level unit test (`it resolves base amounts for a batch of products preserving keys under the ambient market`) targets `ScopedProductBasePriceProvider` directly and is unaffected by the resolver ctor — keep that as a pure unit test.

## Requirements (Test Descriptions)
- [x] `it resolves the per market price amount from the product scoped overrides companion under the ambient market`
- [x] `it falls back to the raw price amount when the product has no market override`
- [x] `it uses the per market currency override when one is configured`
- [x] `it leaves the ambient market scope unchanged after resolving`
- [x] `it overrides the raw base price provider via preference`
- [x] `it resolves base amounts for a batch of products preserving keys under the ambient market`

## Acceptance Criteria
- With `catalog-market` installed, `PriceResolver::resolve()` produces the same market-resolved Money it did before this plan.
- The `#[Preference]` replaces `RawProductBasePriceProvider` in a booted container.
- PHPStan level 8 clean; phpcs clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
