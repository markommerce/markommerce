# markommerce/money-moneyphp

Default Money driver for Markommerce, backed by [moneyphp/money](https://github.com/moneyphp/money).

Provides concrete implementations of `markommerce/money`'s contracts:

- `Markommerce\Money\Moneyphp\Money` — `MoneyInterface` implementation
- `Markommerce\Money\Moneyphp\CurrencyConfig` — `CurrencyConfigInterface` returning `USD` by default
- `Markommerce\Money\Moneyphp\MoneyFactory` — `MoneyFactoryInterface` implementation
- `module.php` — registers `CurrencyConfigInterface` and `MoneyFactoryInterface` bindings

## Requirements

- PHP 8.5+
- `ext-intl` (required for `Money::format()`)

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

## Rebinding the default currency

Override `CurrencyConfigInterface` via a Marko Preference in your application's `module.php`:

```php
return [
    'bindings' => [
        \Markommerce\Money\CurrencyConfigInterface::class => \App\Money\EurCurrencyConfig::class,
    ],
];
```

## Documentation

Full documentation: [markommerce/money-moneyphp](https://markommerce.dev/docs/packages/money-moneyphp/)
