# markommerce/money-moneyphp

Default Money driver for Markommerce, backed by [moneyphp/money](https://github.com/moneyphp/money).

## Installation

```bash
composer require markommerce/money-moneyphp
```

## Quick Example

```php
use Markommerce\Money\MoneyFactoryInterface;

// Resolved via the container (bound by this package's module.php)
$factory = $container->get(MoneyFactoryInterface::class);

$price = $factory->create(1000);         // 1000 minor units, default currency (USD)
$tax   = $price->multiply('0.08');       // 8% tax — numeric string for precision
$total = $price->add($tax);

echo $total->format('en_US');           // e.g. "$1,080.00"
```

## Documentation

Full documentation: [markommerce/money-moneyphp](https://markommerce.dev/docs/packages/money-moneyphp/)
