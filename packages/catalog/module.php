<?php

declare(strict_types=1);

use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeNodeRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Pricing\BasePriceContributor;
use Markommerce\Catalog\Pricing\BatchPriceResolver;
use Markommerce\Catalog\Pricing\Contracts\BatchPriceResolverInterface;
use Markommerce\Catalog\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Catalog\Pricing\Contracts\ProductBasePriceProviderInterface;
use Markommerce\Catalog\Pricing\PriceContributorRegistry;
use Markommerce\Catalog\Pricing\PriceResolver;
use Markommerce\Catalog\Pricing\RawProductBasePriceProvider;
use Markommerce\Catalog\Repositories\CategoryRepository;
use Markommerce\Catalog\Repositories\CategoryTreeNodeRepository;
use Markommerce\Catalog\Repositories\CategoryTreeRepository;
use Markommerce\Catalog\Repositories\ProductCategoryAssignmentRepository;
use Markommerce\Catalog\Repositories\ProductRepository;
use Markommerce\Catalog\Sorting\CategorySortOrderRegistry;
use Markommerce\Catalog\Sorting\ColumnSortOrder;
use Markommerce\Criteria\Sort\SortDirection;

return [
    'bindings' => [
        ProductRepositoryInterface::class                           => ProductRepository::class,
        CategoryRepositoryInterface::class                         => CategoryRepository::class,
        ProductCategoryAssignmentRepositoryInterface::class        => ProductCategoryAssignmentRepository::class,
        CategoryTreeRepositoryInterface::class                     => CategoryTreeRepository::class,
        CategoryTreeNodeRepositoryInterface::class                 => CategoryTreeNodeRepository::class,
        BatchPriceResolverInterface::class                         => BatchPriceResolver::class,
        PriceResolverInterface::class                              => PriceResolver::class,
        ProductBasePriceProviderInterface::class                   => RawProductBasePriceProvider::class,
    ],
    'singletons' => [
        PriceContributorRegistry::class,
        CategorySortOrderRegistry::class,
    ],
    'boot' => function (
        PriceContributorRegistry $priceContributorRegistry,
        BasePriceContributor $basePriceContributor,
        CategorySortOrderRegistry $categorySortOrderRegistry,
    ): void {
        $priceContributorRegistry->register($basePriceContributor, 0);

        $categorySortOrderRegistry->register(new ColumnSortOrder(
            key: 'position',
            label: 'Position',
            column: 'catalog_product_category.position',
            direction: SortDirection::Ascending,
            supportsKeyset: false,
        ), 0);
    },
];
