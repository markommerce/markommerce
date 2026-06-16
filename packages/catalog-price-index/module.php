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
        PriceIndexerInterface::class => static function (ContainerInterface $c): PriceIndexer {
            return new PriceIndexer(
                $c->get(ProductRepositoryInterface::class),
                $c->get(BatchPriceResolverInterface::class),
                $c->get(ProductPriceIndexRepositoryInterface::class),
                $c->get(IndexedMarketsProviderInterface::class),
                $c->get(ScopePassRunner::class),
            );
        },
    ],
    'boot' => function (
        CategorySortOrderRegistry $categorySortOrderRegistry,
        AscendingIndexedPriceSortOrder $ascendingIndexedPriceSortOrder,
        DescendingIndexedPriceSortOrder $descendingIndexedPriceSortOrder,
        IndexerRegistry $indexerRegistry,
        ContainerInterface $container,
    ): void {
        $categorySortOrderRegistry->register($ascendingIndexedPriceSortOrder);
        $categorySortOrderRegistry->register($descendingIndexedPriceSortOrder);
        $indexerRegistry->register('price', static fn (): PriceIndexer => $container->get(PriceIndexerInterface::class));
    },
];
