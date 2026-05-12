# markommerce/money

Money contract package for Markommerce — defines the public interfaces every commerce module uses to handle monetary values. Interface-only: no moneyphp, no `ext-intl`, no concrete classes.

## Installation

```bash
composer require markommerce/money
```

You also need a driver. The default is `markommerce/money-moneyphp`:

```bash
composer require markommerce/money-moneyphp
```

## What it exposes

- `MoneyInterface` — value-object contract: `amount()`, `currency()`, `add()`, `subtract()`, `multiply(string $factor)`, `allocate()`, `equals()`, `greaterThan()`, `lessThan()`, `isZero()`, `format()`
- `MoneyFactoryInterface` — service contract for constructing Money: `create(int $amount, ?string $currency = null)`
- `CurrencyConfigInterface` — service contract for the application's default ISO 4217 currency: `getDefault()`
- `MoneyException` — thrown by implementations for currency mismatch, invalid allocation ratios, invalid multiply factor

> `multiply()` accepts a **numeric string** (e.g. `"1.05"`) for precision-preserving arithmetic.

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
$eurPrice = $this->moneyFactory->create(1000, 'EUR');
```

## Documentation

Full usage, API reference, and examples: [markommerce/money](https://markommerce.dev/docs/packages/money/)
