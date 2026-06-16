<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Pricing\Contracts\BatchPriceResolverInterface;
use Markommerce\Catalog\Sorting\CategorySortOrderRegistry;
use Markommerce\CatalogPriceIndex\Contracts\IndexedMarketsProviderInterface;
use Markommerce\CatalogPriceIndex\Contracts\PriceIndexerInterface;
use Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface;
use Markommerce\CatalogPriceIndex\DefaultIndexedMarketsProvider;
use Markommerce\CatalogPriceIndex\PriceIndexer;
use Markommerce\CatalogPriceIndex\Repositories\ProductPriceIndexRepository;
use Markommerce\CatalogPriceIndex\Sorting\AscendingIndexedPriceSortOrder;
use Markommerce\CatalogPriceIndex\Sorting\DescendingIndexedPriceSortOrder;
use Markommerce\Indexer\Registry\IndexerRegistry;
use Markommerce\Indexer\ScopePassRunner;

return [
    'require' => [
        'marko/core'             => '*',
        'marko/database'         => '*',
        'markommerce/catalog'    => '*',
        'markommerce/currency'   => '*',
        'markommerce/indexer'    => '*',
        'markommerce/money'      => '*',
        'markommerce/scope'      => '*',
    ],
    'bindings' => [
        ProductPriceIndexRepositoryInterface::class => ProductPriceIndexRepository::class,
        IndexedMarketsProviderInterface::class      => DefaultIndexedMarketsProvider::class,
        // Bind PriceIndexerInterface via a factory that also registers with IndexerRegistry.
        // Registration is deferred until PriceIndexerInterface is first resolved, so
        // integration test profiles that only need sort orders are unaffected.
        PriceIndexerInterface::class => static function (ContainerInterface $c): PriceIndexer {
            $indexer = new PriceIndexer(
                $c->get(ProductRepositoryInterface::class),
                $c->get(BatchPriceResolverInterface::class),
                $c->get(ProductPriceIndexRepositoryInterface::class),
                $c->get(IndexedMarketsProviderInterface::class),
                $c->get(ScopePassRunner::class),
            );
            $c->get(IndexerRegistry::class)->register('price', $indexer);

            return $indexer;
        },
    ],
    'singletons' => [
        IndexerRegistry::class,
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
