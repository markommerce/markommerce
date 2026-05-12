# Task 003: Scaffold `markommerce/money-moneyphp` driver + default `Money` value object

**Status**: completed
**Depends on**: 001, 002
**Retry count**: 0

## Description
Create the `markommerce/money-moneyphp` driver package — the default implementation behind `markommerce/money`'s contracts — and implement the `Money` value object backed by `moneyphp/money`. The bindings, `CurrencyConfig`, and `MoneyFactory` follow in task 004; this task ships only the package scaffold and the concrete `Money` class.

## Context
- Working directory: `packages/money-moneyphp/`
- The driver package owns the moneyphp dependency. No other markommerce module references moneyphp directly — they all go through `Markommerce\Money\MoneyInterface`.
- `composer.json` requirements:
  - `require`: `php: ^8.5`, `markommerce/money: self.version`, `marko/core: self.version`, `moneyphp/money: ^4.5`, `ext-intl: *` (hard requirement so `Money::format` localized output always works).
  - `require-dev`: `pestphp/pest: ^4.0`, `marko/testing: self.version`.
  - `config.allow-plugins`: `pestphp/pest-plugin: true`.
  - `autoload`: PSR-4 `Markommerce\Money\Moneyphp\` → `src/`. (Nested under the contract package's namespace — mirrors the `Marko\Database\MySql\` precedent in `marko/database-mysql`, where the driver's namespace lives under the contract package's namespace rather than a flat kebab-to-pascal conversion.)
  - `autoload-dev`: PSR-4 `Markommerce\Money\Moneyphp\Tests\` → `tests/`.
  - `type`: `"marko-module"`.
  - `extra.marko.module`: `true` (so the framework picks up the `module.php` created in task 004).
  - MIT license.
- Add `markommerce/money-moneyphp: self.version` to the root `markommerce/markommerce/composer.json` `require` block (the root metapackage installs the default driver).
- `Money` class — `Markommerce\Money\Moneyphp\Money`:
  - `readonly class` implementing `Markommerce\Money\MoneyInterface`.
  - Constructor: `public function __construct(private int $amount, private string $currency)`. The constructor body builds the internal `Money\Money` instance from moneyphp and stores it in a private property (do not expose it).
  - `amount(): int` returns the original int.
  - `currency(): string` returns the original ISO 4217 string.
  - `add` / `subtract` — delegate to moneyphp; throw `Markommerce\Money\MoneyException::currencyMismatch($this->currency, $other->currency(), 'add'|'subtract')` when operand currency differs. Return a new `Money` instance (or `MoneyInterface`).
  - `multiply(string $factor)` — validate the string is numeric (`is_numeric($factor)`); on failure throw `MoneyException::invalidMultiplyFactor($factor, '...')`. Delegate to moneyphp's `Money::multiply(string)`. Return a new `Money`.
  - `allocate(array $ratios)` — validate ratios are non-empty, all positive, and sum > 0; throw `MoneyException::invalidAllocationRatios('...')` on failure. Delegate to moneyphp's `allocate`, wrap each returned `Money\Money` back into a `Markommerce\Money\Moneyphp\Money`.
  - `equals`, `greaterThan`, `lessThan` — `equals` returns false on currency mismatch; `greaterThan` / `lessThan` throw `MoneyException::currencyMismatch` on mismatch (because returning false would lie about ordering).
  - `isZero()` — delegate to moneyphp.
  - `format(?string $locale = null)` — instantiate moneyphp's `IntlMoneyFormatter` with `NumberFormatter::CURRENCY` and the given locale (or `Locale::getDefault()` if null) and format the wrapped Money.
- `Money` is not `final` (project rule blocks `final`); but every property is private and readonly.
- Cross-implementation interop: `add($other)` must handle the case where `$other` is some non-`Markommerce\Money\Moneyphp\Money` `MoneyInterface` implementation. Read `$other->amount()` / `$other->currency()` from the interface and construct a moneyphp Money for the operation. Do not assume the operand is the same concrete class.

## Requirements (Test Descriptions)
- [ ] `it creates a composer.json for markommerce/money-moneyphp with type marko-module and requires moneyphp ext-intl markommerce/money and marko/core`
- [ ] `it is wired into the root composer.json require block`
- [ ] `it declares the autoload namespace as Markommerce\Money\Moneyphp and the dev namespace under it (nested under the contract package namespace per the Marko\Database\MySql precedent)`
- [ ] `it implements MoneyInterface in a readonly Money class wrapping a private moneyphp Money instance`
- [ ] `it constructs a Money with an integer minor-unit amount and an ISO 4217 currency`
- [ ] `it exposes the original amount and currency via amount and currency accessors`
- [ ] `it adds two Money instances of the same currency and returns a new MoneyInterface`
- [ ] `it adds correctly when the operand is a different MoneyInterface implementation`
- [ ] `it throws MoneyException currencyMismatch from add when operand currency differs`
- [ ] `it subtracts two Money instances of the same currency`
- [ ] `it multiplies by a numeric string factor preserving precision`
- [ ] `it throws MoneyException invalidMultiplyFactor when the factor is not a valid numeric string`
- [ ] `it allocates an amount across positive ratios distributing remainder deterministically`
- [ ] `it throws MoneyException invalidAllocationRatios for empty non-positive or zero-sum ratios`
- [ ] `it returns equality only when both amount and currency match`
- [ ] `it returns false from equals when currencies differ rather than throwing`
- [ ] `it throws MoneyException currencyMismatch from greaterThan and lessThan on currency mismatch`
- [ ] `it reports whether the amount is zero`
- [ ] `it formats the amount using IntlMoneyFormatter with the provided locale and falls back to Locale getDefault when none is given`

## Acceptance Criteria
- File locations:
  - `packages/money-moneyphp/composer.json`
  - `packages/money-moneyphp/src/Money.php`
  - `packages/money-moneyphp/tests/Unit/MoneyTest.php`
- `phpstan` clean at level 8 for `Money.php`.
- No `final`, no magic methods.
- `Money` never exposes the wrapped moneyphp instance publicly.
- All `@throws MoneyException` PHPDoc tags present on methods that can throw.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
