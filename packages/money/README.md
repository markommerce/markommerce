# markommerce/money

Money and Currency value objects for Markommerce — exact BigDecimal arithmetic, immutable, and currency-safe.

## Installation

```bash
composer require markommerce/money
```

## Quick Example

```php
use Markommerce\Money\Currency;
use Markommerce\Money\Money;
use Markommerce\Money\RoundingMode;

$usd   = new Currency(code: 'USD', scale: 2, symbol: '$', name: 'US Dollar');
$price = Money::of('9.99', $usd);
$tax   = Money::of('1.00', $usd);
$total = $price->add($tax); // Money { amount: '10.99', currency: USD }

echo $total->amount();          // '10.99'
echo $total->currency()->code;  // 'USD'
```

## Documentation

Full usage, API reference, and examples: [markommerce/money](https://markommerce.dev/docs/packages/money/)
