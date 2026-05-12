<?php

declare(strict_types=1);

use Markommerce\Catalog\Repository\CategoryRepositoryInterface;
use Markommerce\Catalog\Repository\CategoryRepository;
use Markommerce\Catalog\Repository\ProductRepositoryInterface;
use Markommerce\Catalog\Repository\ProductRepository;
use Markommerce\Catalog\Service\CategoryServiceInterface;
use Markommerce\Catalog\Service\CategoryService;
use Markommerce\Catalog\Service\ProductServiceInterface;
use Markommerce\Catalog\Service\ProductService;
use Markommerce\Catalog\Service\ProductPriceServiceInterface;
use Markommerce\Catalog\Service\ProductPriceService;
use Markommerce\Catalog\Service\CategoryAssignmentServiceInterface;
use Markommerce\Catalog\Service\CategoryAssignmentService;
use Markommerce\Catalog\Repository\ProductCategoryRepositoryInterface;
use Markommerce\Catalog\Repository\ProductCategoryRepository;

return [
    'bindings' => [
        CategoryRepositoryInterface::class => CategoryRepository::class,
        ProductRepositoryInterface::class => ProductRepository::class,
        ProductCategoryRepositoryInterface::class => ProductCategoryRepository::class,
        CategoryServiceInterface::class => CategoryService::class,
        ProductServiceInterface::class => ProductService::class,
        ProductPriceServiceInterface::class => ProductPriceService::class,
        CategoryAssignmentServiceInterface::class => CategoryAssignmentService::class,
    ],
];
