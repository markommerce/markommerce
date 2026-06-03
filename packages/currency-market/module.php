<?php

declare(strict_types=1);

use Markommerce\Currency\Config\CurrencyConfig;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;

return [
    'require' => [
        'markommerce/currency'      => '*',
        'markommerce/config-scope'  => '*',
        'markommerce/market'        => '*',
    ],
    'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
        $scopedFieldRegistry->register(
            entityClass: CurrencyConfig::class,
            property: 'base',
            axes: ['market'],
        );
    },
];
