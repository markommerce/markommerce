# Task 001: Scaffold `markommerce/money` interface package

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the new `markommerce/money` interface package and define the three contracts every commerce module that touches prices will depend on: `MoneyInterface` (value-object contract), `MoneyFactoryInterface` (service contract for constructing Money), and `CurrencyConfigInterface` (service contract for the application's default currency). This package is interface-only — no moneyphp, no `ext-intl`, no concrete classes. The default driver (`markommerce/money-moneyphp`) lives in a separate package (task 003).

## Context
- Working directory: `packages/money/`
- The architecture doc's interface-package rule applies: "Interface packages export only interfaces, exceptions, and value objects. They have no concrete implementations and no database dependencies."
- `MoneyException` (task 002) is the exception part of the contract and ships in this package.
- `composer.json` requirements:
  - `require`: `php: ^8.5`, `marko/core: self.version` (needed by `MarkoException`, which the exception class extends — created in task 002). **Do not** require `moneyphp/money` or `ext-intl` here; those belong to the driver.
  - `require-dev`: `pestphp/pest: ^4.0`, `marko/testing: self.version`.
  - `config.allow-plugins`: `pestphp/pest-plugin: true`.
  - `autoload`: PSR-4 `Markommerce\Money\` → `src/`.
  - `autoload-dev`: PSR-4 `Markommerce\Money\Tests\` → `tests/`.
  - `type`: `"library"` (NOT `marko-module` — there is no `module.php` here; this is a pure interface package).
  - MIT license.
- Add `markommerce/money: self.version` to the root `markommerce/markommerce/composer.json` `require` block.
- `MoneyInterface` declares the full public Money contract. Methods that produce a new Money value return `MoneyInterface` (not `self`), so a Preference can swap the implementation without breaking type compatibility across the system.
  - `amount(): int` — minor units
  - `currency(): string` — ISO 4217 code
  - `add(MoneyInterface $other): MoneyInterface`
  - `subtract(MoneyInterface $other): MoneyInterface`
  - `multiply(string $factor): MoneyInterface` — accepts a numeric string (precision-preserving, matches moneyphp's contract). Implementations throw `MoneyException` on a non-numeric string or invalid factor.
  - `allocate(array $ratios): array` — `@param array<int|float> $ratios`, `@return array<MoneyInterface>`. Ratios must be positive and sum to > 0.
  - `equals(MoneyInterface $other): bool` — true iff both amount and currency match.
  - `greaterThan(MoneyInterface $other): bool` — throws `MoneyException` on currency mismatch.
  - `lessThan(MoneyInterface $other): bool` — throws `MoneyException` on currency mismatch.
  - `isZero(): bool`
  - `format(?string $locale = null): string` — localized string via the driver's chosen formatter (the default driver uses moneyphp's `IntlMoneyFormatter`; when `$locale` is null, falls back to `Locale::getDefault()`).
- `MoneyFactoryInterface::create(int $amount, ?string $currency = null): MoneyInterface` — single factory entry point. The contract intentionally leaves "what happens when `$currency` is null" **driver-defined** — the default driver (task 004) resolves it via the injected `CurrencyConfigInterface`, but a future driver could resolve it differently (env var, request scope, store scope). The interface-level tests in this task assert the method signature and parameter defaults only; assertions about default-resolution behaviour live in the driver's test suite (task 004). This is the only way catalog (and other interface-package consumers) construct Money without coupling to the driver.
- `CurrencyConfigInterface::getDefault(): string` — returns an ISO 4217 string. Carries a class-level `@todo multi-store` docblock noting that the stores module will rebind this to a store-scoped resolver.

## Requirements (Test Descriptions)
- [ ] `it creates a composer.json for markommerce/money with type library and no moneyphp dependency`
- [ ] `it declares the autoload namespace as Markommerce\Money and the dev namespace as Markommerce\Money\Tests`
- [ ] `it requires php 8.5 and marko/core but does not require moneyphp or ext-intl`
- [ ] `it is wired into the root composer.json require block as markommerce/money self.version`
- [ ] `it declares MoneyInterface with amount currency add subtract multiply(string) allocate equals greaterThan lessThan isZero and format methods returning MoneyInterface or matching types`
- [ ] `it declares MoneyFactoryInterface with a single create(int amount, ?string currency = null) method returning MoneyInterface`
- [ ] `it declares CurrencyConfigInterface with a single getDefault method returning a string`
- [ ] `it carries a multi-store refactor docblock on CurrencyConfigInterface`

## Acceptance Criteria
- `composer install` inside Docker resolves cleanly with the new package; `vendor/markommerce/money` becomes a symlink.
- No concrete classes exist anywhere under `packages/money/src/` — only the three interfaces (and the `MoneyException` added in task 002).
- Interface method return types use `MoneyInterface`, not `self` or `static`.
- Tests in `packages/money/tests/Unit/` use reflection to assert interface method signatures and presence of the `@todo multi-store` docblock.
- `phpstan` clean at level 8.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
