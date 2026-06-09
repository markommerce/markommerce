<?php

declare(strict_types=1);

use Markommerce\CatalogMarket\Contracts\CategoryTreeMarketAssignmentRepositoryInterface;
use Markommerce\CatalogMarket\Repositories\CategoryTreeMarketAssignmentRepository;

it(
    'registers CategoryTreeMarketAssignmentRepositoryInterface binding in catalog-market module.php',
    function (): void {
        $module = require dirname(__DIR__, 2) . '/module.php';
    
        expect($module)->toBeArray();
        expect($module)->toHaveKey('bindings');
        expect($module['bindings'])->toHaveKey(CategoryTreeMarketAssignmentRepositoryInterface::class);
        expect($module['bindings'][CategoryTreeMarketAssignmentRepositoryInterface::class])
            ->toBe(CategoryTreeMarketAssignmentRepository::class);
    }
);
