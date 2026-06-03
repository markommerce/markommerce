<?php

declare(strict_types=1);

use Markommerce\Money\Contracts\CurrencyRegistryInterface;
use Markommerce\Money\DefaultCurrencyRegistry;

return [
    'bindings' => [
        CurrencyRegistryInterface::class => DefaultCurrencyRegistry::class,
    ],
];
