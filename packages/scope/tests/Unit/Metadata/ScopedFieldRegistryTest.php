<?php

declare(strict_types=1);

use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Exceptions\UnknownEntityClassException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

function makeFieldRegistryWithAxes(array $knownAxes): ScopeRegistryInterface
{
    return new readonly class ($knownAxes) implements ScopeRegistryInterface
    {
        public function __construct(private readonly array $knownAxes) {}

        public function hasAxis(string $name): bool
        {
            return in_array($name, $this->knownAxes, true);
        }

        public function getAxis(string $name): ScopeAxis
        {
            throw new RuntimeException('Not implemented');
        }

        public function listAxes(): array
        {
            return $this->knownAxes;
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            throw new RuntimeException('Not implemented');
        }
    };
}

it('registers a property with a non-empty list of axes', function (): void {
    $scopeRegistry = makeFieldRegistryWithAxes(['store', 'website']);
    $registry = new ScopedFieldRegistry(scopeRegistry: $scopeRegistry);

    $registry->register(entityClass: stdClass::class, property: 'name', axes: ['store']);

    expect($registry->axesForProperty(entityClass: stdClass::class, property: 'name'))->toBe(['store']);
});

it('returns the registered axes via axesForProperty for the same class and property', function (): void {
    $scopeRegistry = makeFieldRegistryWithAxes(['store', 'website']);
    $registry = new ScopedFieldRegistry(scopeRegistry: $scopeRegistry);

    $registry->register(entityClass: stdClass::class, property: 'name', axes: ['store', 'website']);

    expect($registry->axesForProperty(entityClass: stdClass::class, property: 'name'))->toBe(['store', 'website']);
});

it('returns an empty list from axesForProperty for an unregistered (class, property) pair', function (): void {
    $scopeRegistry = makeFieldRegistryWithAxes(['store', 'website']);
    $registry = new ScopedFieldRegistry(scopeRegistry: $scopeRegistry);

    expect($registry->axesForProperty(entityClass: stdClass::class, property: 'name'))->toBe([]);
});

it('returns the full property-to-axes map via propertiesFor for a registered entity class', function (): void {
    $scopeRegistry = makeFieldRegistryWithAxes(['store', 'website']);
    $registry = new ScopedFieldRegistry(scopeRegistry: $scopeRegistry);

    $registry->register(entityClass: stdClass::class, property: 'name', axes: ['store']);
    $registry->register(entityClass: stdClass::class, property: 'description', axes: ['website']);

    expect($registry->propertiesFor(entityClass: stdClass::class))->toBe([
        'name' => ['store'],
        'description' => ['website'],
    ]);
});

it('returns an empty map from propertiesFor for an entity class with no registrations', function (): void {
    $scopeRegistry = makeFieldRegistryWithAxes(['store', 'website']);
    $registry = new ScopedFieldRegistry(scopeRegistry: $scopeRegistry);

    expect($registry->propertiesFor(entityClass: stdClass::class))->toBe([]);
});

it('reports hasScopedProperties true after at least one registration on a class', function (): void {
    $scopeRegistry = makeFieldRegistryWithAxes(['store', 'website']);
    $registry = new ScopedFieldRegistry(scopeRegistry: $scopeRegistry);

    $registry->register(entityClass: stdClass::class, property: 'name', axes: ['store']);

    expect($registry->hasScopedProperties(entityClass: stdClass::class))->toBeTrue();
});

it('reports hasScopedProperties false for an unregistered class', function (): void {
    $scopeRegistry = makeFieldRegistryWithAxes(['store', 'website']);
    $registry = new ScopedFieldRegistry(scopeRegistry: $scopeRegistry);

    expect($registry->hasScopedProperties(entityClass: stdClass::class))->toBeFalse();
});

it('unions axes (no duplicates) when the same property is registered twice with overlapping axis lists', function (): void {
    $scopeRegistry = makeFieldRegistryWithAxes(['store', 'website', 'locale']);
    $registry = new ScopedFieldRegistry(scopeRegistry: $scopeRegistry);

    $registry->register(entityClass: stdClass::class, property: 'name', axes: ['store', 'website']);
    $registry->register(entityClass: stdClass::class, property: 'name', axes: ['website', 'locale']);

    expect($registry->axesForProperty(entityClass: stdClass::class, property: 'name'))->toBe(['store', 'website', 'locale']);
});

it('preserves first-registration axis order and appends new axes from later registrations in their declared order', function (): void {
    $scopeRegistry = makeFieldRegistryWithAxes(['store', 'website', 'locale']);
    $registry = new ScopedFieldRegistry(scopeRegistry: $scopeRegistry);

    $registry->register(entityClass: stdClass::class, property: 'name', axes: ['locale', 'store']);
    $registry->register(entityClass: stdClass::class, property: 'name', axes: ['website', 'locale']);

    expect($registry->axesForProperty(entityClass: stdClass::class, property: 'name'))->toBe(['locale', 'store', 'website']);
});

it('is a no-op when the same property is registered twice with the exact same axes (idempotent)', function (): void {
    $scopeRegistry = makeFieldRegistryWithAxes(['store', 'website']);
    $registry = new ScopedFieldRegistry(scopeRegistry: $scopeRegistry);

    $registry->register(entityClass: stdClass::class, property: 'name', axes: ['store', 'website']);
    $registry->register(entityClass: stdClass::class, property: 'name', axes: ['store', 'website']);

    expect($registry->axesForProperty(entityClass: stdClass::class, property: 'name'))->toBe(['store', 'website']);
});

it('throws UnknownAxisException when register is called with an axis not present in ScopeRegistryInterface', function (): void {
    $scopeRegistry = makeFieldRegistryWithAxes(['store', 'website']);
    $registry = new ScopedFieldRegistry(scopeRegistry: $scopeRegistry);

    expect(fn () => $registry->register(entityClass: stdClass::class, property: 'name', axes: ['unknown_axis']))
        ->toThrow(UnknownAxisException::class);
});

it('accepts an empty axis list and treats the registration as a no-op (the property does not appear in propertiesFor and hasScopedProperties stays false if it was the only registration)', function (): void {
    $scopeRegistry = makeFieldRegistryWithAxes(['store', 'website']);
    $registry = new ScopedFieldRegistry(scopeRegistry: $scopeRegistry);

    $registry->register(entityClass: stdClass::class, property: 'name', axes: []);

    expect($registry->propertiesFor(entityClass: stdClass::class))->toBe([])
        ->and($registry->hasScopedProperties(entityClass: stdClass::class))->toBeFalse();
});

it('validates every axis in a multi-axis registration so a list mixing valid and unknown axes still throws UnknownAxisException', function (): void {
    $scopeRegistry = makeFieldRegistryWithAxes(['store', 'website']);
    $registry = new ScopedFieldRegistry(scopeRegistry: $scopeRegistry);

    expect(fn () => $registry->register(entityClass: stdClass::class, property: 'name', axes: ['store', 'unknown_axis']))
        ->toThrow(UnknownAxisException::class);
});

it('throws UnknownEntityClassException when register is called with a class name that does not exist (caught typos in bridge module.php files)', function (): void {
    $scopeRegistry = makeFieldRegistryWithAxes(['store', 'website']);
    $registry = new ScopedFieldRegistry(scopeRegistry: $scopeRegistry);

    expect(fn () => $registry->register(entityClass: 'NonExistentClass\That\Does\NotExist', property: 'name', axes: ['store']))
        ->toThrow(UnknownEntityClassException::class);
});

it('validates class existence before axis validation so a registration with both a typo class name and an unknown axis surfaces the class error first', function (): void {
    $scopeRegistry = makeFieldRegistryWithAxes(['store', 'website']);
    $registry = new ScopedFieldRegistry(scopeRegistry: $scopeRegistry);

    expect(fn () => $registry->register(entityClass: 'NonExistentClass\That\Does\NotExist', property: 'name', axes: ['unknown_axis']))
        ->toThrow(UnknownEntityClassException::class);
});
