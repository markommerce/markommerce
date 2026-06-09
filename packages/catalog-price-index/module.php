<?php

declare(strict_types=1);

use Markommerce\Catalog\Sorting\CategorySortOrderRegistry;
use Markommerce\CatalogPriceIndex\Contracts\IndexedMarketsProviderInterface;
use Markommerce\CatalogPriceIndex\Contracts\PriceIndexerInterface;
use Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface;
use Markommerce\CatalogPriceIndex\DefaultIndexedMarketsProvider;
use Markommerce\CatalogPriceIndex\PriceIndexer;
use Markommerce\CatalogPriceIndex\Repositories\ProductPriceIndexRepository;
use Markommerce\CatalogPriceIndex\Sorting\AscendingIndexedPriceSortOrder;
use Markommerce\CatalogPriceIndex\Sorting\DescendingIndexedPriceSortOrder;

return [
    'require' => [
        'marko/core'             => '*',
        'marko/database'         => '*',
        'markommerce/catalog'    => '*',
        'markommerce/currency'   => '*',
        'markommerce/money'      => '*',
        'markommerce/scope'      => '*',
    ],
    'bindings' => [
        ProductPriceIndexRepositoryInterface::class => ProductPriceIndexRepository::class,
        IndexedMarketsProviderInterface::class      => DefaultIndexedMarketsProvider::class,
        PriceIndexerInterface::class                => PriceIndexer::class,
    ],
    'boot' => function (
        CategorySortOrderRegistry $categorySortOrderRegistry,
        AscendingIndexedPriceSortOrder $ascendingIndexedPriceSortOrder,
        DescendingIndexedPriceSortOrder $descendingIndexedPriceSortOrder,
    ): void {
        $categorySortOrderRegistry->register($ascendingIndexedPriceSortOrder);
        $categorySortOrderRegistry->register($descendingIndexedPriceSortOrder);
    },
];
