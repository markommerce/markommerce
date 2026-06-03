---
title: markommerce/money
description: Money and Currency value objects for Markommerce — exact BigDecimal arithmetic, immutable, and currency-safe.
---

Money and Currency value objects for Markommerce. `markommerce/money` provides two readonly value objects --- `Currency` and `Money` --- along with a `CurrencyRegistryInterface` contract and a `DefaultCurrencyRegistry` implementation. All arithmetic is exact (no floating-point rounding errors) using `brick/math` internally; brick types are never exposed through the public API. `Money` is tax-agnostic: it carries an amount and a currency, nothing else.

## Installation

```bash
composer require markommerce/money
```

## Usage

### Currency

`Currency` is a readonly value object that carries the ISO 4217 code, decimal scale (number of sub-unit digits), symbol, and name. The constructor validates the code against the `/^[A-Z]{3}$/` pattern and throws `InvalidCurrencyException` for non-conforming inputs:

```php
use Markommerce\Money\Currency;

$usd = new Currency(code: 'USD', scale: 2, symbol: '$', name: 'US Dollar');
$jpy = new Currency(code: 'JPY', scale: 0, symbol: '¥', name: 'Japanese Yen');

echo $usd->code;   // 'USD'
echo $usd->scale;  // 2
echo $jpy->scale;  // 0 — yen has no sub-unit
```

### CurrencyRegistryInterface

The registry resolves an ISO 4217 code string into a `Currency` instance. The default implementation ships the full set of circulating ISO 4217 currencies (~156 — excluding precious-metal, fund/bond, and test codes), loaded from a bundled static dataset (no `ext-intl` runtime dependency). Minor units follow the official ISO 4217 standard (e.g. JPY scale 0, BHD/IQD scale 3). Swap it with your own via a Marko Preference bound to `CurrencyRegistryInterface::class`:

```php
use Markommerce\Money\DefaultCurrencyRegistry;
use Markommerce\Money\Exceptions\UnknownCurrencyException;

$registry = new DefaultCurrencyRegistry();

$eur = $registry->get('EUR');   // Currency { code: 'EUR', scale: 2, ... }
$registry->has('USD');           // true
$registry->has('XYZ');           // false
$registry->all();                // array<string, Currency> keyed by code

// Unknown codes throw loudly
$registry->get('XYZ');           // throws UnknownCurrencyException
```

### Money

`Money` wraps an exact decimal amount and a `Currency`. All operations are immutable --- each returns a new `Money` instance and leaves the original unchanged. Arithmetic across different currencies throws `CurrencyMismatchException`:

```php
use Markommerce\Money\Money;
use Markommerce\Money\RoundingMode;

$price = Money::of('9.99', $usd);
$tax   = Money::of('1.00', $usd);
$total = $price->add($tax);            // Money { amount: '10.99', currency: USD }

$halved = $total->divide(2, RoundingMode::HalfUp);    // Money { amount: '5.50' }
$scaled = $total->multiply('1.1', RoundingMode::Up);  // Money { amount: '12.09' }
```

Allocate splits a `Money` value across a set of integer ratios without losing any sub-unit remainder (no pennies lost):

```php
use Markommerce\Money\Money;

$total = Money::of('10.99', $usd);
$parts = $total->allocate([1, 1]); // [Money{'5.50'}, Money{'5.49'}]
```

Minor-unit construction reads an integer amount already expressed in the currency's smallest sub-unit --- useful when reading `decimal(20,4)` strings from the database in minor-unit form:

```php
use Markommerce\Money\Money;

$price = Money::ofMinor(999, $usd); // Money { amount: '9.99', currency: USD }
```

Comparison helpers:

```php
use Markommerce\Money\Money;

$a = Money::of('5.00', $usd);
$b = Money::of('3.00', $usd);

$a->isZero();       // false
$a->isPositive();   // true
$a->isNegative();   // false
$a->equals($b);     // false
$a->compareTo($b);  // 1 (positive: $a > $b)
```

## API Reference

### `Currency`

Readonly value object. The constructor validates and uppercases `$code`.

| Property | Type | Description |
|---|---|---|
| `$code` | `string` | ISO 4217 code, uppercased (e.g. `'USD'`). |
| `$scale` | `int` | Number of decimal places for the currency's minor unit (e.g. `2` for USD, `0` for JPY). |
| `$symbol` | `string` | Display symbol (e.g. `'$'`). |
| `$name` | `string` | Human-readable name (e.g. `'US Dollar'`). |

| Method | Return type | Throws | Description |
|---|---|---|---|
| `equals(Currency $currency)` | `bool` | --- | Returns `true` when both codes match. |

### `CurrencyRegistryInterface`

| Method | Return type | Throws | Description |
|---|---|---|---|
| `get(string $code)` | `Currency` | `UnknownCurrencyException` | Resolve an ISO 4217 code to a `Currency`. |
| `has(string $code)` | `bool` | --- | Return `true` when the code is registered. |
| `all()` | `array<string, Currency>` | --- | Return all registered currencies keyed by code. |

`DefaultCurrencyRegistry` ships all circulating ISO 4217 currencies (~156) from a bundled static dataset, with official ISO 4217 minor units. Extend or replace it via Marko Preferences.

### `Money`

Readonly value object. Constructed via static factory methods; direct `new` is not available.

| Method | Return type | Throws | Description |
|---|---|---|---|
| `Money::of(string\|int $amount, Currency $currency)` | `Money` | --- | Construct from a decimal string or integer amount. |
| `Money::ofMinor(int $minor, Currency $currency)` | `Money` | --- | Construct from an integer minor-unit amount scaled by `$currency->scale`. |
| `amount()` | `string` | --- | Return the decimal amount string at the currency's scale. |
| `currency()` | `Currency` | --- | Return the associated `Currency`. |
| `add(Money $other)` | `Money` | `CurrencyMismatchException` | Add two same-currency amounts. |
| `subtract(Money $other)` | `Money` | `CurrencyMismatchException` | Subtract two same-currency amounts. |
| `multiply(string\|int $factor, RoundingMode $mode)` | `Money` | --- | Multiply by a scalar and round to the currency's scale. |
| `divide(string\|int $divisor, RoundingMode $mode)` | `Money` | `DivisionByZeroException` | Divide by a scalar and round to the currency's scale. |
| `allocate(array<int> $ratios)` | `array<Money>` | --- | Split across integer ratios; distributes remainders without loss. |
| `equals(Money $other)` | `bool` | `CurrencyMismatchException` | Compare amounts and currencies for equality. |
| `compareTo(Money $other)` | `int` | `CurrencyMismatchException` | Return `-1`, `0`, or `1`. |
| `isZero()` | `bool` | --- | Return `true` when amount is exactly zero. |
| `isPositive()` | `bool` | --- | Return `true` when amount is greater than zero. |
| `isNegative()` | `bool` | --- | Return `true` when amount is less than zero. |

### `RoundingMode`

Pure enum mapping to brick/math rounding modes. Used wherever `Money` performs a lossy operation.

| Case | Description |
|---|---|
| `HalfUp` | Round toward nearest neighbor; ties round away from zero. |
| `HalfDown` | Round toward nearest neighbor; ties round toward zero. |
| `HalfEven` | Round toward nearest even neighbor (banker's rounding). |
| `Up` | Round away from zero. |
| `Down` | Round toward zero (truncate). |
| `Ceiling` | Round toward positive infinity. |
| `Floor` | Round toward negative infinity. |
| `Unnecessary` | Assert that no rounding is necessary; throws if rounding would be required. |

### Exceptions

All exceptions extend `MarkoException` and carry `message`, `context`, and `suggestion`.

| Exception | Named constructor(s) | When thrown |
|---|---|---|
| `InvalidCurrencyException` | `forInvalidCode(string $code)`, `forNegativeScale(int $scale)` | `Currency` constructor receives a non-ISO-4217 code or a negative scale. |
| `CurrencyMismatchException` | `forMismatch(Currency $expected, Currency $actual)` | Arithmetic or comparison between two `Money` instances with different currencies. |
| `DivisionByZeroException` | `forDivisionByZero()` | `Money::divide()` receives `0` as divisor. |
| `UnknownCurrencyException` | `forCode(string $code)` | `CurrencyRegistryInterface::get()` is called with an unregistered code. |

## Related Packages

- [markommerce/money-intl](/docs/packages/money-intl/) --- Locale-aware formatting of `Money` values via `ext-intl`
- [markommerce/currency](/docs/packages/currency/) --- Config-driven base currency resolver
- [markommerce/pricing](/docs/packages/pricing/) --- Pricing pipeline that resolves a product's price as a `Money` value
