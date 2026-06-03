<?php

declare(strict_types=1);
use Markommerce\Catalog\Entity\Product;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

it('still boots an empty ScopedFieldRegistry when the boot closure runs (no-op field registration preserved)', function (): void {
    $fakeScopeRegistry = new class () implements ScopeRegistryInterface
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

    $registry = new ScopedFieldRegistry(scopeRegistry: $fakeScopeRegistry);
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module)->toHaveKey('boot');
    expect($module['boot'])->toBeCallable();

    $module['boot']($registry);

    expect($registry->hasScopedProperties(Product::class))->toBeFalse();
});
