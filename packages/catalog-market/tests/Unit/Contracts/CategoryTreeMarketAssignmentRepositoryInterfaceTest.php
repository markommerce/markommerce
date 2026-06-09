<?php

declare(strict_types=1);

use Marko\Database\Repository\RepositoryInterface;
use Markommerce\CatalogMarket\Contracts\CategoryTreeMarketAssignmentRepositoryInterface;

it(
    'CategoryTreeMarketAssignmentRepositoryInterface lives in Markommerce\\CatalogMarket\\Contracts namespace and preserves its findByMarket and findByTree signatures',
    function (): void {
        $reflection = new ReflectionClass(CategoryTreeMarketAssignmentRepositoryInterface::class);
    
        expect($reflection->isInterface())->toBeTrue();
        expect($reflection->getNamespaceName())->toBe('Markommerce\\CatalogMarket\\Contracts');
        expect($reflection->implementsInterface(RepositoryInterface::class))->toBeTrue();
    
        $ownMethods = array_map(
            fn (ReflectionMethod $m) => $m->getName(),
            array_filter(
                $reflection->getMethods(),
                fn (ReflectionMethod $m) => $m->getDeclaringClass()->getName() === CategoryTreeMarketAssignmentRepositoryInterface::class,
            ),
        );
    
        expect($ownMethods)->toContain('findByMarket');
        expect($ownMethods)->toContain('findByTree');
        expect(count($ownMethods))->toBe(2);
    
        $findByMarket = $reflection->getMethod('findByMarket');
        $marketParams = $findByMarket->getParameters();
        expect($marketParams)->toHaveCount(1);
        expect($marketParams[0]->getName())->toBe('market');
        expect((string) $marketParams[0]->getType())->toBe('string');
    
        $findByTree = $reflection->getMethod('findByTree');
        $treeParams = $findByTree->getParameters();
        expect($treeParams)->toHaveCount(1);
        expect($treeParams[0]->getName())->toBe('treeId');
        expect((string) $treeParams[0]->getType())->toBe('int');
    }
);
