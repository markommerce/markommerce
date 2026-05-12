<?php

declare(strict_types=1);

use Markommerce\Money\CurrencyConfigInterface;
use Markommerce\Money\MoneyFactoryInterface;
use Markommerce\Money\Moneyphp\CurrencyConfig;
use Markommerce\Money\Moneyphp\MoneyFactory;

return [
    'bindings' => [
        CurrencyConfigInterface::class => CurrencyConfig::class,
        MoneyFactoryInterface::class => MoneyFactory::class,
    ],
];
