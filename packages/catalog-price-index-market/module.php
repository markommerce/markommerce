<?php

declare(strict_types=1);

use Markommerce\CatalogPriceIndex\Entity\ProductPriceIndexEntry;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;

return [
    'require' => [
        'markommerce/catalog-market'        => '*',
        'markommerce/catalog-price-index'   => '*',
        'markommerce/market'                => '*',
        'markommerce/scope'                 => '*',
    ],
    'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
        $scopedFieldRegistry->register(
            entityClass: ProductPriceIndexEntry::class,
            property: 'amount',
            axes: ['market'],
        );
    },
];
