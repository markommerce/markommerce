<?php

declare(strict_types=1);

it('still boots an empty ScopedFieldRegistry when the boot closure runs (no-op field registration preserved)', function (): void {
    $fakeScopeRegistry = new class implements \Markommerce\Scope\Registry\ScopeRegistryInterface {
        public function hasAxis(string $name): bool
        {
            return true;
        }

        public function getAxis(string $name): \Markommerce\Scope\Axis\ScopeAxis
        {
            return new \Markommerce\Scope\Axis\ScopeAxis(
                name: $name,
                hierarchy: \Markommerce\Scope\Hierarchy\ScopeHierarchy::fromPaths(['default']),
                default: 'default',
            );
        }

        public function listAxes(): array
        {
            return [];
        }

        public function getHierarchy(string $axisName): \Markommerce\Scope\Hierarchy\ScopeHierarchy
        {
            return \Markommerce\Scope\Hierarchy\ScopeHierarchy::fromPaths(['default']);
        }
    };

    $registry = new \Markommerce\Scope\Metadata\ScopedFieldRegistry(scopeRegistry: $fakeScopeRegistry);
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module)->toHaveKey('boot');
    expect($module['boot'])->toBeCallable();

    $module['boot']($registry);

    expect($registry->hasScopedProperties(\Markommerce\Catalog\Entity\Product::class))->toBeFalse();
});
