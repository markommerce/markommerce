<?php

declare(strict_types=1);

use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeNodeRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Repositories\CategoryRepository;
use Markommerce\Catalog\Repositories\CategoryTreeNodeRepository;
use Markommerce\Catalog\Repositories\CategoryTreeRepository;
use Markommerce\Catalog\Repositories\ProductCategoryAssignmentRepository;
use Markommerce\Catalog\Repositories\ProductRepository;

return [
    'bindings' => [
        ProductRepositoryInterface::class                           => ProductRepository::class,
        CategoryRepositoryInterface::class                         => CategoryRepository::class,
        ProductCategoryAssignmentRepositoryInterface::class        => ProductCategoryAssignmentRepository::class,
        CategoryTreeRepositoryInterface::class                     => CategoryTreeRepository::class,
        CategoryTreeNodeRepositoryInterface::class                 => CategoryTreeNodeRepository::class,
    ],
];
