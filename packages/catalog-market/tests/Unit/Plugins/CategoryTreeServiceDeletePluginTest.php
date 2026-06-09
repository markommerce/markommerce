<?php

declare(strict_types=1);

use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Markommerce\Catalog\Contracts\CategoryTreeServiceInterface;
use Markommerce\Catalog\Services\CategoryTreeService;
use Markommerce\CatalogMarket\Entity\CategoryTreeMarketAssignment;
use Markommerce\CatalogMarket\Exceptions\TreeHasMarketAssignmentsException;
use Markommerce\CatalogMarket\Plugins\CategoryTreeServiceDeletePlugin;
use Markommerce\CatalogMarket\Tests\Support\FakeCategoryTreeMarketAssignmentRepository;

it('throws TreeHasMarketAssignmentsException when the tree has at least one market assignment', function (): void {
    $repository = new FakeCategoryTreeMarketAssignmentRepository();

    $assignment = new CategoryTreeMarketAssignment();
    $assignment->market = 'us';
    $assignment->treeId = 1;
    $repository->save($assignment);

    $plugin = new CategoryTreeServiceDeletePlugin(
        categoryTreeMarketAssignmentRepository: $repository,
    );

    expect(fn () => $plugin->beforeDeleteTree(1))
        ->toThrow(TreeHasMarketAssignmentsException::class);
});

it('returns silently when the tree has no market assignments', function (): void {
    $repository = new FakeCategoryTreeMarketAssignmentRepository();

    $plugin = new CategoryTreeServiceDeletePlugin(
        categoryTreeMarketAssignmentRepository: $repository,
    );

    $result = $plugin->beforeDeleteTree(999);

    expect($result)->toBeNull();
});

it('lists every assigned market in the exception message in stable order', function (): void {
    $repository = new FakeCategoryTreeMarketAssignmentRepository();

    $us = new CategoryTreeMarketAssignment();
    $us->market = 'us';
    $us->treeId = 1;

    $eu = new CategoryTreeMarketAssignment();
    $eu->market = 'eu';
    $eu->treeId = 1;

    $repository->save($us);
    $repository->save($eu);

    $plugin = new CategoryTreeServiceDeletePlugin(
        categoryTreeMarketAssignmentRepository: $repository,
    );

    try {
        $plugin->beforeDeleteTree(1);
        fail('Expected TreeHasMarketAssignmentsException to be thrown');
    } catch (TreeHasMarketAssignmentsException $e) {
        expect($e->getMessage())->toContain('us');
        expect($e->getMessage())->toContain('eu');
    }
});

it(
    'declares the Plugin attribute targeting Markommerce\\Catalog\\Contracts\\CategoryTreeServiceInterface',
    function (): void {
        $reflection = new ReflectionClass(CategoryTreeServiceDeletePlugin::class);
        $attributes = $reflection->getAttributes(Plugin::class);
    
        expect($attributes)->toHaveCount(1);
    
        $pluginAttribute = $attributes[0]->newInstance();
    
        expect($pluginAttribute->target)->toBe(CategoryTreeServiceInterface::class);
    }
);

it('declares the Before attribute targeting the deleteTree method', function (): void {
    $reflection = new ReflectionClass(CategoryTreeServiceDeletePlugin::class);
    $method = $reflection->getMethod('beforeDeleteTree');
    $attributes = $method->getAttributes(Before::class);

    expect($attributes)->toHaveCount(1);

    $beforeAttribute = $attributes[0]->newInstance();

    expect($beforeAttribute->method)->toBe('deleteTree');
});

it('accepts the same int $treeId parameter that CategoryTreeService::deleteTree expects', function (): void {
    $reflection = new ReflectionClass(CategoryTreeServiceDeletePlugin::class);
    $method = $reflection->getMethod('beforeDeleteTree');
    $params = $method->getParameters();

    expect($params)->toHaveCount(1);
    expect($params[0]->getName())->toBe('treeId');
    expect((string) $params[0]->getType())->toBe('int');

    $serviceReflection = new ReflectionClass(CategoryTreeService::class);
    $serviceMethod = $serviceReflection->getMethod('deleteTree');
    $serviceParams = $serviceMethod->getParameters();

    expect($serviceParams)->toHaveCount(1);
    expect($serviceParams[0]->getName())->toBe($params[0]->getName());
    expect((string) $serviceParams[0]->getType())->toBe((string) $params[0]->getType());
});
