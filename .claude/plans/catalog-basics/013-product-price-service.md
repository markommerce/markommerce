# Task 013: Create ProductPriceServiceInterface and ProductPriceService

**Status**: completed
**Depends on**: 001, 008
**Retry count**: 0

## Description
Add the dedicated service that resolves a `Product`'s base price into a `MoneyInterface`. The entity is currency-unaware (stores only `basePriceAmount` as int minor units); this service is the single cross-module entry point for "what is this product's price?". It is also the natural future home for discount, tax, store-currency, and tier-pricing logic.

## Context
- Related files:
  - `packages/catalog/src/Service/ProductPriceServiceInterface.php`
  - `packages/catalog/src/Service/ProductPriceService.php`
- The entity (task 008) intentionally has no `getBasePrice()` method. Construction of `Money` from a product lives here.
- **Catalog cannot construct `Money` directly** — `Markommerce\Money\Moneyphp\Money` lives in the driver package and catalog only depends on `markommerce/money`. The service injects `MoneyFactoryInterface` (from `markommerce/money`, task 001) and delegates construction to it. This keeps catalog driver-agnostic.
- Interface:
  - `getBasePrice(Product $product): MoneyInterface`
- Default implementation:
  - Constructor: `MoneyFactoryInterface $moneyFactory`
  - `getBasePrice(Product $product): MoneyInterface` returns `$this->moneyFactory->create($product->basePriceAmount)` — passes no `$currency` argument so the factory uses the configured default.
- The service is intentionally narrow today; **do not** pre-build hooks for discounts/taxes/scope (YAGNI). Future logic plugs in through Marko Preferences (replace the binding) or Plugins (decorate `getBasePrice`).
- Carry a class-level `@todo multi-store` docblock on the default `ProductPriceService` noting that currency resolution will become store-scoped in the stores/config module.

## Requirements (Test Descriptions)
- [ ] `it declares getBasePrice on ProductPriceServiceInterface returning MoneyInterface`
- [ ] `it delegates Money construction to MoneyFactoryInterface create with the product basePriceAmount`
- [ ] `it passes no currency argument to MoneyFactory so the factory's default currency is used`
- [ ] `it returns a MoneyInterface whose amount matches the product basePriceAmount`
- [ ] `it carries a multi-store refactor docblock on the default implementation`
- [ ] `it does not construct any concrete Money class directly`

## Acceptance Criteria
- This task **creates** `packages/catalog/tests/Support/FakeMoney.php` and `packages/catalog/tests/Support/FakeMoneyFactory.php` (both later reused by task 015's `ProductService` tests). `FakeMoney` is a minimal `MoneyInterface` stub: only `amount()`, `currency()`, `equals()`, and `isZero()` carry real behaviour (constructor takes amount + currency); `add`, `subtract`, `multiply`, `allocate`, `greaterThan`, `lessThan`, and `format` throw `\LogicException('not implemented in FakeMoney')`. This is intentional — service tests never need the math, and a hand-rolled arithmetic fake would risk diverging from the driver. `FakeMoneyFactory` returns `FakeMoney` instances and records its `create()` calls so tests can assert that the service passed the right amount and the right (or null) currency.
- Tests in `packages/catalog/tests/Unit/Service/ProductPriceServiceTest.php` use `FakeMoneyFactory`. No PHPUnit mocks. No real `Money` from the driver package — catalog tests must work without `markommerce/money-moneyphp` installed.
- Service is not `final`.
- `phpstan` clean at level 8.
- Constructor parameter name follows the project rule (`$moneyFactory` for `MoneyFactoryInterface`).

## Implementation Notes
(Left blank — filled in by programmer during implementation)
