# markommerce/money

Money contract package for Markommerce — defines the public interfaces every commerce module uses to handle monetary values. Interface-only: no moneyphp, no `ext-intl`, no concrete classes.

## Installation

```bash
composer require markommerce/money
```

## Quick Example

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

## Documentation

Full usage, API reference, and examples: [markommerce/money](https://markommerce.dev/docs/packages/money/)
