# Task 010: catalog-price-index-market — market-axis bridge

**Status**: completed
**Depends on**: 007, 008
**Retry count**: 0

> Dependency note: this bridge's `#[Preference(replaces: DefaultIndexedMarketsProvider::class)]` and its `implements IndexedMarketsProviderInterface` both reference symbols DEFINED in T008 (`DefaultIndexedMarketsProvider`, `IndexedMarketsProviderInterface`). T007 only provides the entity/repo/migration. Therefore T010 depends on BOTH 007 (entity for the axis registration) and 008 (the markets-provider seam it overrides).

## Description
Create the `markommerce/catalog-price-index-market` bridge package that adds the `market` axis to the price index. It registers the index entry's `amount` property on the `market` axis (so `ScopeResolver->resolved($entry,'amount')` reads per-market overrides from the `scopes` JSON) and overrides the markets provider via `#[Preference]` to feed the indexer the actual market list. Installing this bridge is what makes the index "know about markets" — without it the index is base-only.

## Context
- **Package scaffold** mirrors `catalog-market` (thin bridge): `composer.json` (`markommerce/catalog-price-index-market`, PSR-4 `Markommerce\CatalogPriceIndexMarket\`, require `markommerce/catalog-price-index`, `markommerce/scope`, `markommerce/market`, **`markommerce/catalog-market`**), `module.php` with a `boot`, `src/`, `tests/`.
  - **Why require `catalog-market`:** the per-market index passes in T008 only produce market-DIFFERENTIATED amounts when the `ScopedProductBasePriceProvider` (`#[Preference]` from catalog-market, T003) is active. Without `catalog-market` installed, every per-market pass resolves to the same RAW base amount and the index would write redundant identical overrides — defeating the bridge's purpose. Declaring `catalog-market` as a require makes the market-aware index actually market-aware. (catalog-market in turn pulls in catalog-scope/market.)
  - **Root wiring:** add `markommerce/catalog-price-index-market` to the ROOT `composer.json` `require` list, and add its `Markommerce\\CatalogPriceIndexMarket\\Tests\\` mapping to the root `autoload-dev` block (mirror every other package).
- **Axis registration** in `module.php` `boot` (mirror catalog-market's `ScopedFieldRegistry->register`):
  ```php
  'boot' => function (ScopedFieldRegistry $r): void {
      $r->register(entityClass: ProductPriceIndexEntry::class, property: 'amount', axes: ['market']);
  },
  ```
  This lets `ScopeResolver->resolved($entry, 'amount')` walk the `scopes` JSON for the active market (the future sort read path), falling back to the base `amount`.
- **Markets provider** `packages/catalog-price-index-market/src/ScopedIndexedMarketsProvider.php`:
  ```php
  #[Preference(replaces: DefaultIndexedMarketsProvider::class)]
  class ScopedIndexedMarketsProvider implements IndexedMarketsProviderInterface
  {
      public function __construct(private ScopeRegistryInterface $scopeRegistry) {}

      /** @return list<string> */
      public function markets(): array
      {
          try {
              $defaultMarket = $this->scopeRegistry->getAxis('market')->default;
              $paths = $this->scopeRegistry->getHierarchy('market')->paths();
          } catch (UnknownAxisException) {
              return [];
          }

          // all market paths except the default — the base pass already covers the default
          return array_values(array_filter(
              $paths,
              fn (string $m): bool => $m !== $defaultMarket,
          ));
      }
  }
  ```
  - **The default market is NOT on `ScopeHierarchy`.** `ScopeHierarchy::paths()` returns the path list but exposes no default; the default lives on `ScopeAxis::$default`, reachable via `ScopeRegistryInterface::getAxis('market')->default`. So inject `ScopeRegistryInterface` and call BOTH `getAxis('market')->default` (the name to exclude) and `getHierarchy('market')->paths()` (the full list). Excluding the default matters because `ScopeWalker` filters the default path out when reading (`$p !== $axisDefault`), so a default-market override would be dead data — and the base pass already covers it.
  - Both `getAxis` and `getHierarchy` throw `UnknownAxisException` if the `market` axis is absent — catch it and return `[]` (add the `@throws`-free guard shown above). Add the matching `@throws` PHPDoc only if any path can still propagate.
- With this bridge installed, T008's indexer per-market passes now iterate the real markets and write per-market `amount` overrides; reading them back through `ScopeResolver->resolved($entry,'amount')` under an active market returns the market amount.

## Requirements (Test Descriptions)
- [x] `it registers the index amount on the market axis`
- [x] `it resolves a per market index amount through the scope resolver`
- [x] `it falls back to the base index amount when no market override exists`
- [x] `it returns the configured markets excluding the default`
- [x] `it overrides the default markets provider via preference`
- [x] `it returns no markets when the market axis is absent`

## Acceptance Criteria
- After boot, `ScopeResolver->resolved($entry,'amount')` is market-aware for `ProductPriceIndexEntry`.
- The `#[Preference]` replaces `DefaultIndexedMarketsProvider`.
- PHPStan level 8 clean; phpcs clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
