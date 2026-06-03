<?php

declare(strict_types=1);

/**
 * catalog-market module manifest.
 *
 * Registers Product.priceAmount on the market axis so per-market base prices
 * fall back to the global product price when no market override is set.
 */

use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogMarket\Contracts\CategoryTreeMarketAssignmentRepositoryInterface;
use Markommerce\CatalogMarket\Repositories\CategoryTreeMarketAssignmentRepository;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;

return [
    'require' => [
        'markommerce/catalog-scope' => '*',
        'markommerce/market'        => '*',
        'markommerce/catalog'       => '*',
    ],
    'bindings' => [
        CategoryTreeMarketAssignmentRepositoryInterface::class => CategoryTreeMarketAssignmentRepository::class,
    ],
    'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
        $scopedFieldRegistry->register(
            entityClass: Product::class,
            property: 'priceAmount',
            axes: ['market'],
        );
    },
];
