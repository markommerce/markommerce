<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Markommerce\CatalogMarket\Exceptions\TreeHasMarketAssignmentsException;

it('TreeHasMarketAssignmentsException lives in Markommerce\\CatalogMarket\\Exceptions namespace with the same forTreeId static factory shape', function (): void {
    $reflection = new ReflectionClass(TreeHasMarketAssignmentsException::class);

    expect($reflection->getNamespaceName())->toBe('Markommerce\\CatalogMarket\\Exceptions');
    expect($reflection->isSubclassOf(MarkoException::class))->toBeTrue();

    $method = $reflection->getMethod('forTreeId');
    expect($method->isStatic())->toBeTrue();

    $params = $method->getParameters();
    expect($params)->toHaveCount(2);
    expect($params[0]->getName())->toBe('treeId');
    expect((string) $params[0]->getType())->toBe('int');
    expect($params[1]->getName())->toBe('markets');
});

it('TreeHasMarketAssignmentsException::forTreeId reports the markets that still reference the tree', function (): void {
    $exception = TreeHasMarketAssignmentsException::forTreeId(3, ['us', 'eu']);

    expect($exception)->toBeInstanceOf(TreeHasMarketAssignmentsException::class)
        ->and($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toContain('3')
        ->and($exception->getMessage())->toContain('us')
        ->and($exception->getMessage())->toContain('eu')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
