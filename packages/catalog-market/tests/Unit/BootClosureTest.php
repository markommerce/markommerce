<?php

declare(strict_types=1);
use Markommerce\Catalog\Entity\Product;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

function makeBootClosureTestFakeScopeRegistry(): ScopeRegistryInterface
{
    return new class () implements ScopeRegistryInterface
    {
        public function hasAxis(string $name): bool
        {
            return true;
        }

        public function getAxis(string $name): ScopeAxis
        {
            return new ScopeAxis(
                name: $name,
                hierarchy: ScopeHierarchy::fromPaths(['default']),
                default: 'default',
            );
        }

        public function listAxes(): array
        {
            return [];
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            return ScopeHierarchy::fromPaths(['default']);
        }
    };
}

it('registers the product price amount on the market axis', function (): void {
    $registry = new ScopedFieldRegistry(scopeRegistry: makeBootClosureTestFakeScopeRegistry());
    $module = require dirname(__DIR__, 2) . '/module.php';

    $module['boot']($registry);

    expect($registry->axesForProperty(Product::class, 'priceAmount'))->toBe(['market']);
});

it('boot closure has a callable boot key typed on ScopedFieldRegistry', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module)->toHaveKey('boot')
        ->and($module['boot'])->toBeCallable();

    $reflection = new ReflectionFunction($module['boot']);
    $parameters = $reflection->getParameters();

    expect($parameters)->toHaveCount(1);

    $type = $parameters[0]->getType();
    expect($type)->not->toBeNull()
        ->and((string) $type)->toBe(ScopedFieldRegistry::class);
});
