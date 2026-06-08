# Task 006: catalog — `BatchPriceResolver` + `PriceResolver` delegates to a batch of one

**Status**: completed
**Depends on**: 003, 004, 005
**Retry count**: 0

> Dependency note: depends on 003 because this task changes `PriceResolver`'s constructor and must update/keep-green the catalog-market scoped tests created in T003 (they construct or resolve `PriceResolver`). Building T006 before T003 exists would leave those tests to break later with no owner.

## Description
Build `BatchPriceResolver`, the single source of truth that runs every registered contributor over a `PriceBatch` and turns the accumulated amounts into `Money`. Then collapse `PriceResolver::resolve()` into a thin wrapper that builds a batch of one and delegates — guaranteeing display pricing and index pricing run the identical pipeline.

## Context
- `packages/catalog/src/Pricing/Contracts/BatchPriceResolverInterface.php`:
  ```php
  interface BatchPriceResolverInterface
  {
      /**
       * @param array<array-key, Product> $products
       * @return array<array-key, Money>   only keys with a non-null resolved amount; null-amount keys omitted
       */
      public function resolve(array $products): array;
  }
  ```
- `BatchPriceResolver` deps: `PriceContributorRegistry` + `CurrencyResolver`. Steps: build `PriceBatch::of($products, $this->currencyResolver->base())`; run `foreach ($this->registry->all() as $c) { $c->contribute($batch); }`; for each key whose `$batch->amount($key)` is non-null, map to `Money::of($amount, $batch->currency())`; omit null-amount keys from the result. (Currency is resolved once for the whole batch — the ambient market is fixed per batch.)
- Register binding in `module.php`: `BatchPriceResolverInterface::class => BatchPriceResolver::class`.
- Refactor `PriceResolver` (`#[Preference]`-able impl of `PriceResolverInterface`, unchanged interface): drop the direct provider call; new dep `BatchPriceResolverInterface`. `resolve(PriceContext $context)`: `$result = $this->batchPriceResolver->resolve([0 => $context->product]); return $result[0] ?? throw PriceUnavailableException::forContext($context);`. Keep `PriceResolverInterface` and `PriceUnavailableException` exactly as-is for consumers.
- All existing `PriceResolver` behavior tests (raw resolve, null→exception) must still pass; the scoped ones live in catalog-market (T003) and still pass since the scoped provider sits behind `BasePriceContributor`.
- **Backward-compat sweep for the constructor change.** Changing `PriceResolver`'s constructor from `(ProductBasePriceProviderInterface, CurrencyResolver)` (T002 era) to `(BatchPriceResolverInterface)` will break any test that hand-constructs `new PriceResolver(...)` — notably the catalog tests from T002 and the moved catalog-market tests from T003. As part of THIS task: grep `new PriceResolver(` across `packages/catalog*/tests`, and update every construction site to the new single-arg ctor OR (preferred) switch those tests to resolve `PriceResolverInterface` from a booted container so they are ctor-agnostic going forward. The plan's worker for T006 must run the catalog AND catalog-market suites, not just catalog's.

## Requirements (Test Descriptions)
- [x] `it resolves money for every product with a base amount`
- [x] `it omits products that have no resolvable amount from the result`
- [x] `it applies contributors in priority order to the batch`
- [x] `it resolves the whole batch using a single base currency lookup`
- [x] `it returns money keyed by the caller supplied product keys`
- [x] `it resolves a single product price by delegating to a batch of one`
- [x] `it throws PriceUnavailableException when the batch yields no amount for the product`
- [x] `it produces the same money for a product whether resolved singly or in a batch`

## Acceptance Criteria
- `PriceResolver` no longer talks to the provider/registry directly — only `BatchPriceResolverInterface`.
- Single-resolve and batch-resolve yield identical Money for the same product (asserted).
- PHPStan level 8 clean; phpcs clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
