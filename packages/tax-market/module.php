<?php

declare(strict_types=1);

use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Tax\Config\TaxConfig;

return [
    'require' => [
        'markommerce/tax'          => '*',
        'markommerce/config-scope' => '*',
        'markommerce/market'       => '*',
    ],
    'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
        $scopedFieldRegistry->register(
            entityClass: TaxConfig::class,
            property: 'pricesIncludeTax',
            axes: ['market'],
        );
    },
];
