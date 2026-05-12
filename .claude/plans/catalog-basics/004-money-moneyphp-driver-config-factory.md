# Task 004: `markommerce/money-moneyphp` — default `CurrencyConfig` + `MoneyFactory` + `module.php`

**Status**: completed
**Depends on**: 003
**Retry count**: 0

## Description
Round out the `markommerce/money-moneyphp` driver with the default `CurrencyConfig` (returns USD), the `MoneyFactory` that constructs `Money` instances using the configured default currency, and the `module.php` that binds both interfaces to their default implementations so the framework wires them up automatically.

## Context
- Files to create:
  - `packages/money-moneyphp/src/CurrencyConfig.php`
  - `packages/money-moneyphp/src/MoneyFactory.php`
  - `packages/money-moneyphp/module.php`
- `CurrencyConfig` — `Markommerce\Money\Moneyphp\CurrencyConfig`:
  - Implements `Markommerce\Money\CurrencyConfigInterface`.
  - `getDefault(): string` returns the constant `'USD'`.
  - Class-level `@todo multi-store` docblock: "The stores/config module will rebind `CurrencyConfigInterface` to a store-scoped resolver. Until then this default is global."
  - Not `final`.
- `MoneyFactory` — `Markommerce\Money\Moneyphp\MoneyFactory`:
  - Implements `Markommerce\Money\MoneyFactoryInterface`.
  - Constructor: `public function __construct(private CurrencyConfigInterface $currencyConfig) {}` (parameter name follows the interface-minus-`Interface` rule).
  - `create(int $amount, ?string $currency = null): MoneyInterface` — **resolves the default at every call**, not once at construction. When `$currency` is null, calls `$this->currencyConfig->getDefault()` inside `create()`; constructs and returns a new `Markommerce\Money\Moneyphp\Money($amount, $currency)`. This per-call resolution matters once a future stores module rebinds `CurrencyConfigInterface` to a request-scoped or store-scoped resolver — a cached-once value would silently go stale.
  - Not `final`.
- `module.php` — minimal, mirrors `marko/admin-auth/module.php`:
  - Returns an array with a `'bindings'` key.
  - Bindings:
    - `CurrencyConfigInterface::class => CurrencyConfig::class`
    - `MoneyFactoryInterface::class => MoneyFactory::class`
  - No closure factories needed — both implementations are constructor-injectable and the container resolves dependencies.

## Requirements (Test Descriptions)
- [ ] `it implements CurrencyConfigInterface in CurrencyConfig`
- [ ] `it returns USD from CurrencyConfig getDefault`
- [ ] `it carries a multi-store refactor docblock on CurrencyConfig`
- [ ] `it implements MoneyFactoryInterface in MoneyFactory`
- [ ] `it creates a Money with the explicit currency when one is passed to MoneyFactory create`
- [ ] `it creates a Money with the CurrencyConfig default currency when no currency is passed to MoneyFactory create`
- [ ] `it calls CurrencyConfig getDefault on every create invocation rather than caching once at construction (assert by mutating FakeCurrencyConfig between two create calls and confirming both currencies match the mutated value)`
- [ ] `it uses the integer amount verbatim on the returned Money`
- [ ] `it returns an array with a bindings key from module.php`
- [ ] `it binds CurrencyConfigInterface to CurrencyConfig in module.php`
- [ ] `it binds MoneyFactoryInterface to MoneyFactory in module.php`

## Acceptance Criteria
- Tests in `packages/money-moneyphp/tests/Unit/CurrencyConfigTest.php`, `packages/money-moneyphp/tests/Unit/MoneyFactoryTest.php`, and `packages/money-moneyphp/tests/Unit/ModuleTest.php`.
- `ModuleTest` loads `module.php` via `require`, asserts the returned array shape, and for each binding asserts `interface_exists($key)` + `class_exists($value)` + the class implements the interface via `ReflectionClass::implementsInterface`.
- No PHPUnit mocks — `MoneyFactoryTest` uses a hand-written `FakeCurrencyConfig` (returns whatever the test sets) for the currency-default scenarios.
- `phpstan` clean at level 8.
- No `final`.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
