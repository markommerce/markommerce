---
title: markommerce/money-moneyphp
description: Default Money driver for Markommerce, backed by moneyphp/money.
---

Default Money driver for Markommerce, backed by [moneyphp/money](https://github.com/moneyphp/money). This package provides concrete implementations of `markommerce/money`'s contracts and registers them in the Marko container — making it the out-of-the-box choice for monetary arithmetic in any Markommerce application.

## Requirements

- PHP 8.5+
- `ext-intl` (required for `Money::format()`)

## Installation

```bash
composer require markommerce/money-moneyphp
```

## What It Provides

| Class | Implements |
|-------|-----------|
| `Markommerce\Money\Moneyphp\Money` | `MoneyInterface` |
| `Markommerce\Money\Moneyphp\CurrencyConfig` | `CurrencyConfigInterface` — returns `USD` by default |
| `Markommerce\Money\Moneyphp\MoneyFactory` | `MoneyFactoryInterface` |
| `module.php` | Registers `CurrencyConfigInterface` and `MoneyFactoryInterface` bindings in the Marko container |

## Usage

```php
use Markommerce\Money\MoneyFactoryInterface;

// Resolved via the container (bound by this package's module.php)
$factory = $container->get(MoneyFactoryInterface::class);

$price = $factory->create(1000);         // 1000 minor units, default currency (USD)
$tax   = $price->multiply('0.08');       // 8% tax — numeric string for precision
$total = $price->add($tax);

echo $total->format('en_US');           // e.g. "$1,080.00"
```

## Configuration

### Rebinding the Default Currency

`CurrencyConfig` returns `USD` by default. Override it with a Marko Preference in your application's `module.php`:

```php title="module.php"
return [
    'bindings' => [
        \Markommerce\Money\CurrencyConfigInterface::class => \App\Money\EurCurrencyConfig::class,
    ],
];
```

Your custom `EurCurrencyConfig` simply implements `CurrencyConfigInterface::getDefault()` and returns your desired ISO 4217 code.

> The `CurrencyConfig` class is tagged `@todo multi-store`: the stores/config module will rebind `CurrencyConfigInterface` to a store-scoped resolver. Until then, the default currency is global.

## API Reference

### `MoneyFactory`

```php
use Markommerce\Money\CurrencyConfigInterface;
use Markommerce\Money\MoneyInterface;
use Markommerce\Money\Moneyphp\MoneyFactory;

public function __construct(private CurrencyConfigInterface $currencyConfig) {}

public function create(int $amount, ?string $currency = null): MoneyInterface
```

When `$currency` is `null`, `MoneyFactory` calls `CurrencyConfigInterface::getDefault()` to resolve the currency. This means changing the bound `CurrencyConfigInterface` implementation is the only configuration needed to switch the application's default currency.

### `CurrencyConfig`

```php
use Markommerce\Money\Moneyphp\CurrencyConfig;

public function getDefault(): string  // returns 'USD'
```

## Related Packages

- [markommerce/money](/docs/packages/money/) — the contract package this driver implements
