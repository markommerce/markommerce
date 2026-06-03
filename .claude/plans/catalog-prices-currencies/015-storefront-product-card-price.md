# Task 015: catalog-storefront — show formatted price on the product card

**Status**: complete
**Depends on**: 012, 008, 014
**Retry count**: 0

## Description
Display each product's price on the storefront product card, resolved via the pricing pipeline and formatted for the active locale. Targets the base (Tier-1, global-currency) storefront; per-market scoped price display in `catalog-storefront-scope` is a separate future extension (out of scope here).

## Context
- **Dependencies (orchestrator-wired):** `catalog-storefront/composer.json` must `require` `markommerce/pricing` and `markommerce/money-intl` (both `self.version`). The orchestrator adds these and runs `composer update` BEFORE this task runs — do NOT edit the root `composer.json` or run composer update/install yourself. Autoloading for `Markommerce\Pricing\*` and `Markommerce\MoneyIntl\*` will be active.
- **Component:** `packages/catalog-storefront/src/Component/ProductCard.php` currently has no constructor and builds `ProductCardData` from a bare `Product`. Add constructor injection of `Markommerce\Pricing\Contracts\PriceResolverInterface` and `Markommerce\MoneyIntl\MoneyFormatter` (follow the DI pattern in `ProductGridComponent`). In `data(Product $product)`:
  - Resolve: `$money = $priceResolver->resolve(PriceContext::forProduct($product));` then `$formatted = $moneyFormatter->format($money);`.
  - A product with no price throws `PriceUnavailableException` from `resolve()` — CATCH it and set the formatted price to `null` (card simply omits the price). Add `@throws`-free handling (the catch makes `data()` not propagate it).
- **DTO:** `packages/catalog-storefront/src/Data/ProductCardData.php` — add a `?string $formattedPrice` property (nullable; null = no price to show). Keep it a `readonly class`/`ExtensibleData` as today.
- **Template:** `packages/catalog-storefront/resources/views/components/product-card.latte` — render the price when present, e.g. after the heading:
  ```latte
  {if $formattedPrice}
      <mk-text class="catalog-product-card__price">{$formattedPrice}</mk-text>
  {/if}
  ```
  Match the existing component markup/conventions (`mk-*` elements).
- **Grid wiring:** the product grid renders cards. Inspect `ProductGridComponent` + `ProductGridData` + the grid Latte template to see how per-product values (e.g. `resolvedNames` keyed by product id) reach each card, and thread a `formattedPrices` map (keyed by product id) the SAME way so the grid passes each card its formatted price. Resolve prices via the same `PriceResolverInterface`/`MoneyFormatter` (inject into `ProductGridComponent`). Mirror the existing `resolvedNames`/`resolvedDescs` pattern exactly.
- **Locale:** `MoneyFormatter::format()` reads the active locale from `ScopeContext` (falls back to a default). The container's ICU may not apply locale-specific separators — assert structural output (currency symbol + digits), not locale-specific separators, as the `money-intl` tests do.
- **Tests:** mirror `packages/catalog-storefront/tests/Unit/Component/ProductGridComponentTest.php` (`productGridBuildComponent()`, `productGridBuildLatte()`, fake repositories). Cover: card shows the formatted price for a priced product; card omits the price for a product with no `priceAmount`; the grid exposes `formattedPrices` keyed by product id; the rendered template contains the formatted price string.

## Requirements (Test Descriptions)
- [x] `it resolves and formats the product price into the product card data`
- [x] `it leaves the formatted price null when the product has no price amount`
- [x] `it renders the formatted price in the product card template`
- [x] `it omits the price element from the card when there is no price`
- [x] `it exposes formatted prices keyed by product id from the product grid`
- [x] `it renders the price for each card in the product grid`

## Acceptance Criteria
- `catalog-storefront` requires `markommerce/pricing` + `markommerce/money-intl`.
- `ProductCardData` carries a nullable `formattedPrice`; the card template shows it only when present.
- A product with a price displays a locale-formatted price; a product with no price shows no price element (no error surfaces).
- The product grid passes each card its formatted price (via a `formattedPrices` map, mirroring `resolvedNames`).
- All requirements have passing tests; coverage ≥ 80%; existing catalog-storefront tests still pass.
- Follows standards.

## Implementation Notes
- `ProductCardData` gained a nullable `?string $formattedPrice = null` property (with default so existing callers don't break).
- `ProductCard` now injects `PriceResolverInterface` and `MoneyFormatter`; `data()` catches `PriceUnavailableException` and sets `formattedPrice = null`.
- `product-card.latte` renders `<mk-text class="catalog-product-card__price">` inside `{if $formattedPrice}` block.
- `ProductGridData` gained `array $formattedPrices = []` (keyed by product id, values `string|null`).
- `ProductGridComponent` injects `PriceResolverInterface` and `MoneyFormatter`; populates `formattedPrices` in the same foreach loop as `resolvedNames`/`resolvedDescs`, catching `PriceUnavailableException` per product.
- `ScopedProductGridComponent` updated to accept and pass-through the new pricing deps to `parent::__construct()` and carry `formattedPrices` in its re-built `ProductGridData`.
- Feature and unit tests in `catalog-storefront` updated to wire the new dependencies (using a no-price resolver + default MoneyFormatter for Tier-1/scope-free context).
- PHPStan level 8 passes; php-cs-fixer applied.
