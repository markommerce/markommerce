<?php

declare(strict_types=1);

use Markommerce\CatalogMarketCategoryTrees\Contracts\CategoryTreeMarketAssignmentRepositoryInterface;
use Markommerce\CatalogMarketCategoryTrees\Repositories\CategoryTreeMarketAssignmentRepository;

return [
    'bindings' => [
        CategoryTreeMarketAssignmentRepositoryInterface::class => CategoryTreeMarketAssignmentRepository::class,
    ],
];
