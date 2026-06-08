# Task 005: catalog — contributor interface, registry, and base contributor

**Status**: completed
**Depends on**: 002, 004
**Retry count**: 0

## Description
Define the set-wise pricing contributor seam: a `PriceContributorInterface` that mutates a whole `PriceBatch` in one call, a boot-registered singleton `PriceContributorRegistry` ordered by priority, and the `BasePriceContributor` that seeds each product's base amount from the `ProductBasePriceProviderInterface`. This is the single extension point future tax/promo/tier packages implement once — and it MUST be N+1-free (one set-wise call per batch, never per product).

## Context
- `packages/catalog/src/Pricing/Contracts/PriceContributorInterface.php`:
  ```php
  interface PriceContributorInterface
  {
      public function contribute(PriceBatch $batch): void;   // set-wise: read batch->products(), write batch->setAmount(...)
  }
  ```
- `packages/catalog/src/Pricing/BasePriceContributor.php` implements it: `$amounts = $this->basePriceProvider->amountsFor($batch->products()); foreach ($amounts as $key => $amount) { $batch->setAmount($key, $amount); }`. ONE call to `amountsFor` for the whole batch.
- `packages/catalog/src/Pricing/PriceContributorRegistry.php` — a **singleton** (registered in `module.php` `singletons`): `register(PriceContributorInterface $contributor, int $priority = 0): void` and `all(): list<PriceContributorInterface>` returning contributors sorted by ascending priority (base = lowest, e.g. 0; future markups run after). Stable order for equal priorities.
- Register the registry as a singleton and, in catalog's `module.php` `boot`, register `BasePriceContributor` with priority 0. Future packages call `$registry->register($theirContributor, $priority)` in their own boot.
- **Structural note:** catalog's `module.php` currently returns ONLY a `bindings` key (no `singletons`, no `boot`). This task must add both:
  - `'singletons' => [PriceContributorRegistry::class]` (so the registry resolves as ONE shared instance);
  - `'boot' => function (PriceContributorRegistry $registry, BasePriceContributor $base): void { $registry->register($base, 0); }`.
  Confirm the framework's module loader invokes a catalog `boot` closure (the `DependencyResolver` + boot-loop only call `boot` when the manifest exposes one — other modules like catalog-market already do this, so the pattern is supported). The `BasePriceContributor` resolves through the container using the `ProductBasePriceProviderInterface` binding (raw in catalog, scoped once catalog-market's `#[Preference]` is active).
- Document in a class-level docblock the N+1 contract: a contributor receiving a batch of N MUST issue a query count independent of N (load its data with one set-wise query keyed by the batch's product ids).

## Requirements (Test Descriptions)
- [x] `it seeds the base amount for every product in the batch`
- [x] `it seeds null for a product with no base amount`
- [x] `it calls the base price provider once for the whole batch`
- [x] `it registers a contributor and returns it from all`
- [x] `it orders contributors by ascending priority`
- [x] `it preserves registration order for contributors of equal priority`
- [x] `it is the same registry instance across container resolutions`
- [x] `it registers the base price contributor at boot with the lowest priority`

## Acceptance Criteria
- `PriceContributorRegistry` resolves as one shared singleton.
- `BasePriceContributor` invokes the provider exactly once per batch (asserted with a counting fake).
- PHPStan level 8 clean; phpcs clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
