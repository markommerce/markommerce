<?php

declare(strict_types=1);

it('moves all src classes to Markommerce\\CatalogMarket\\ namespace and they are autoloadable', function (): void {
    $classes = [
        \Markommerce\CatalogMarket\Contracts\CategoryTreeMarketAssignmentRepositoryInterface::class,
        \Markommerce\CatalogMarket\Entity\CategoryTreeMarketAssignment::class,
        \Markommerce\CatalogMarket\Exceptions\TreeHasMarketAssignmentsException::class,
        \Markommerce\CatalogMarket\Plugins\CategoryTreeServiceDeletePlugin::class,
        \Markommerce\CatalogMarket\Repositories\CategoryTreeMarketAssignmentRepository::class,
        \Markommerce\CatalogMarket\Services\CategoryTreeMarketAssignmentService::class,
        \Markommerce\CatalogMarket\Services\CategoryTreeMarketResolver::class,
    ];

    foreach ($classes as $class) {
        expect(class_exists($class) || interface_exists($class))
            ->toBeTrue("Class or interface $class should be autoloadable under Markommerce\\CatalogMarket\\ namespace");
    }
});
