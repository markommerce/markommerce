---
title: markommerce/money
description: Money contract package for Markommerce — defines the public interfaces every commerce module uses to handle monetary values.
---

Money contract package for Markommerce — defines the public interfaces every commerce module uses to handle monetary values. This package is interface-only: no moneyphp dependency, no `ext-intl`, no concrete classes. Every commerce module that deals with prices depends on this package; the actual arithmetic is delegated to a driver.

## Installation

```bash
composer require markommerce/money
```

You also need a driver. The default is `markommerce/money-moneyphp`:

```bash
composer require markommerce/money-moneyphp
```

## What It Exposes

- `MoneyInterface` — value-object contract for monetary amounts
- `MoneyFactoryInterface` — service contract for constructing `MoneyInterface` instances
- `CurrencyConfigInterface` — service contract for the application's default ISO 4217 currency
- `MoneyException` — thrown by implementations for currency mismatch, invalid allocation ratios, and invalid multiply factors

## Usage

Never construct `Money` directly — inject `MoneyFactoryInterface` and use the factory:

```php
use Markommerce\Money\MoneyFactoryInterface;
use Markommerce\Money\MoneyInterface;

class MyService
{
    public function __construct(
        private MoneyFactoryInterface $moneyFactory,
    ) {}

    public function computeTotal(int $unitPriceMinorUnits, int $quantity): MoneyInterface
    {
        return $this->moneyFactory
            ->create($unitPriceMinorUnits)
            ->multiply((string) $quantity);
    }
}
```

Create with an explicit currency:

```php
use Markommerce\Money\MoneyFactoryInterface;

$eurPrice = $this->moneyFactory->create(1000, 'EUR');
```

> `multiply()` accepts a **numeric string** (e.g. `"1.05"`) for precision-preserving arithmetic.

## API Reference

### `MoneyInterface`

Value-object contract representing an immutable monetary amount. All arithmetic methods return a new instance.

| Method | Signature | Description |
|--------|-----------|-------------|
| `amount` | `amount(): int` | Returns the amount in minor units (e.g. cents). |
| `currency` | `currency(): string` | Returns the ISO 4217 currency code. |
| `add` | `add(MoneyInterface $money): MoneyInterface` | Returns a new instance with the sum. Throws `MoneyException` on currency mismatch. |
| `subtract` | `subtract(MoneyInterface $money): MoneyInterface` | Returns a new instance with the difference. Throws `MoneyException` on currency mismatch. |
| `multiply` | `multiply(string $factor): MoneyInterface` | Multiplies by a numeric string factor. Throws `MoneyException` on non-numeric or invalid factor. |
| `allocate` | `allocate(array $ratios): array` | Splits the amount by ratios — returns `array<MoneyInterface>`. Ratios must be positive and sum to > 0. Throws `MoneyException` on invalid ratios. |
| `equals` | `equals(MoneyInterface $money): bool` | Returns `true` if amount and currency are equal. |
| `greaterThan` | `greaterThan(MoneyInterface $money): bool` | Returns `true` if this amount is greater. Throws `MoneyException` on currency mismatch. |
| `lessThan` | `lessThan(MoneyInterface $money): bool` | Returns `true` if this amount is less. Throws `MoneyException` on currency mismatch. |
| `isZero` | `isZero(): bool` | Returns `true` if the amount is zero. |
| `format` | `format(?string $locale = null): string` | Returns a locale-formatted string (e.g. `"$49.99"`). |

### `MoneyFactoryInterface`

| Method | Signature | Description |
|--------|-----------|-------------|
| `create` | `create(int $amount, ?string $currency = null): MoneyInterface` | Creates a `MoneyInterface` from minor units. When `$currency` is `null`, the driver resolves the default from `CurrencyConfigInterface`. |

### `CurrencyConfigInterface`

| Method | Signature | Description |
|--------|-----------|-------------|
| `getDefault` | `getDefault(): string` | Returns the application's default ISO 4217 currency code. |

> This interface is tagged `@todo multi-store`: the stores module will rebind it to a store-scoped resolver so that each store can return its own configured default currency.

### `MoneyException`

Extends `MarkoException` (which exposes `message`, `context`, and `suggestion`). Named constructors:

| Factory Method | Thrown When |
|----------------|-------------|
| `currencyMismatch(string $expected, string $actual, string $operation)` | `add()`, `subtract()`, `greaterThan()`, or `lessThan()` is called with mismatched currencies. |
| `invalidAllocationRatios(string $reason)` | `allocate()` receives an empty array, negative values, or ratios that sum to zero. |
| `invalidMultiplyFactor(string $factor, string $reason)` | `multiply()` receives a non-numeric string. |

## Related Packages

- [markommerce/money-moneyphp](/docs/packages/money-moneyphp/) — default driver backed by moneyphp/money
