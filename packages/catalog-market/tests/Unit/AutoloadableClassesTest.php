<?php

declare(strict_types=1);
use Markommerce\CatalogMarket\Contracts\CategoryTreeMarketAssignmentRepositoryInterface;
use Markommerce\CatalogMarket\Entity\CategoryTreeMarketAssignment;
use Markommerce\CatalogMarket\Exceptions\TreeHasMarketAssignmentsException;
use Markommerce\CatalogMarket\Plugins\CategoryTreeServiceDeletePlugin;
use Markommerce\CatalogMarket\Repositories\CategoryTreeMarketAssignmentRepository;
use Markommerce\CatalogMarket\Services\CategoryTreeMarketAssignmentService;
use Markommerce\CatalogMarket\Services\CategoryTreeMarketResolver;

it('moves all src classes to Markommerce\\CatalogMarket\\ namespace and they are autoloadable', function (): void {
    $classes = [
        CategoryTreeMarketAssignmentRepositoryInterface::class,
        CategoryTreeMarketAssignment::class,
        TreeHasMarketAssignmentsException::class,
        CategoryTreeServiceDeletePlugin::class,
        CategoryTreeMarketAssignmentRepository::class,
        CategoryTreeMarketAssignmentService::class,
        CategoryTreeMarketResolver::class,
    ];

    foreach ($classes as $class) {
        expect(class_exists($class) || interface_exists($class))
            ->toBeTrue("Class or interface $class should be autoloadable under Markommerce\\CatalogMarket\\ namespace");
    }
});
