# Task 002: Money value object + RoundingMode

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Implement the BigDecimal-backed `Money` value object and the `RoundingMode` enum. `Money` is immutable, exact, and refuses ambiguous or unsafe operations loudly. This is the core arithmetic type the whole pricing stack builds on.

## Context
- Files: `packages/money/src/Money.php`, `packages/money/src/RoundingMode.php`, `packages/money/src/Exceptions/CurrencyMismatchException.php`, `packages/money/src/Exceptions/RoundingRequiredException.php`.
- `Money` is a `readonly class` wrapping a `Brick\Math\BigDecimal $amount` + `Currency $currency`. Never expose `BigDecimal` directly in a way that leaks the dependency choice — expose `amount(): string` (decimal string) and `currency(): Currency`.
- Named constructors: `Money::of(string|int $amount, Currency $currency)` and `Money::ofMinor(int $minor, Currency $currency)` (minor units → major using `currency.scale`). No public `__construct` magic beyond what's needed.
- `RoundingMode` is a backed enum wrapping/mapping to `brick/math` rounding modes (e.g. `HalfUp`, `HalfEven`, `Down`, `Up`, `Ceiling`, `Floor`) so callers never import `brick/math` directly.
- Arithmetic returns NEW `Money` instances (immutability):
  - `add(Money $other)` / `subtract(Money $other)` — require identical currency; throw `CurrencyMismatchException` (extends `MarkoException`) otherwise.
  - `multiply(string|int $factor, RoundingMode $mode)` and `divide(string|int $divisor, RoundingMode $mode)` — `RoundingMode` is REQUIRED; calling without it is impossible by signature, and a division by zero throws loudly.
  - `allocate(array $ratios): array<Money>` — splits the amount across integer ratios with no lost minor units (remainder distributed deterministically).
- Comparison/sign helpers: `equals`, `isZero`, `isPositive`, `isNegative`, `compareTo` — comparisons across different currencies throw `CurrencyMismatchException`.
- All amounts kept at full precision internally; rounding only happens where a `RoundingMode` is supplied.

## Requirements (Test Descriptions)
- [x] `it creates money from a decimal string and a currency`
- [x] `it creates money from minor units using the currency scale`
- [x] `it adds two money amounts of the same currency`
- [x] `it throws CurrencyMismatchException when adding different currencies`
- [x] `it multiplies by a factor using an explicit rounding mode`
- [x] `it divides by a divisor using an explicit rounding mode`
- [x] `it throws when dividing by zero`
- [x] `it allocates an amount across ratios without losing minor units`
- [x] `it reports equality only for same currency and amount`
- [x] `it returns a new immutable instance from every arithmetic operation`

## Acceptance Criteria
- All requirements have passing tests; coverage ≥ 80%.
- No public API exposes `brick/math` types directly (only `string`/`RoundingMode`/`Money`/`Currency`).
- `@throws` tags on every throwing method; no `final`; strict types.

## Implementation Notes
- `Money` is a `readonly class` with a private `BigDecimal $bigDecimal` property; `amount()` returns `(string) $bigDecimal->toScale($currency->scale)` so the decimal string always has the correct number of decimal places.
- `RoundingMode` enum maps to `Brick\Math\RoundingMode` via `toBrick()` — callers never import brick types.
- `ofMinor()` uses `BigDecimal::ofUnscaledValue($minor, $scale)` to convert minor units to major.
- `allocate()` works on integer minor units via `getUnscaledValue()->toInt()`, distributes remainder deterministically to front of array.
- `DivisionByZeroException` is a custom `MarkoException`; brick's `DivisionByZeroException` is caught internally and re-thrown.
- PHPStan level 8 passes; phpcs/php-cs-fixer auto-fixed formatting (string interpolation, multi-line method signatures).
