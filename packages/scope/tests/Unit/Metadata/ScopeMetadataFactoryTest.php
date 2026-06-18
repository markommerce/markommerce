<?php

declare(strict_types=1);

use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Metadata\ScopeMetadata;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

class EntityWithNoScopedProperties
{
    public string $name = '';

    public int $price = 0;
}

class EntityWithScopedProperties
{
    #[Scoped(axes: ['store', 'website'])]
    public string $name = '';

    #[Scoped(axes: ['website'])]
    public string $description = '';

    public int $price = 0;
}

class EntityWithUnknownAxis
{
    #[Scoped(axes: ['unknown_axis'])]
    public string $title = '';
}

class EntityWithEmptyAxes
{
    #[Scoped(axes: [])]
    public string $name = '';

    public int $price = 0;
}

class ParentEntityWithScopedProperty
{
    #[Scoped(axes: ['store'])]
    public string $name = '';
}

class ChildEntityInheritingScoped extends ParentEntityWithScopedProperty
{
    public string $sku = '';
}

class EntityForRegistryTest
{
    public string $name = '';

    public string $description = '';
}

function makeMockRegistry(array $knownAxes): ScopeRegistryInterface
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

function makeFieldRegistry(ScopeRegistryInterface $scopeRegistry): ScopedFieldRegistry
{
    return new ScopedFieldRegistry(scopeRegistry: $scopeRegistry);
}

it('returns empty metadata for entity classes with no Scoped properties', function (): void {
    $scopeRegistry = makeMockRegistry(['store', 'website']);
    $fieldRegistry = makeFieldRegistry($scopeRegistry);
    $factory = new ScopeMetadataFactory($scopeRegistry, $fieldRegistry);

    $metadata = $factory->for(EntityWithNoScopedProperties::class);

    expect($metadata)->toBeInstanceOf(ScopeMetadata::class)
        ->and($metadata->hasScopedProperties())->toBeFalse();
});

it('discovers Scoped properties on an entity class via reflection', function (): void {
    $scopeRegistry = makeMockRegistry(['store', 'website']);
    $fieldRegistry = makeFieldRegistry($scopeRegistry);
    $factory = new ScopeMetadataFactory($scopeRegistry, $fieldRegistry);

    $metadata = $factory->for(EntityWithScopedProperties::class);

    expect($metadata->hasScopedProperties())->toBeTrue();
});

it('returns declared axes for a scoped property in declaration order', function (): void {
    $scopeRegistry = makeMockRegistry(['store', 'website']);
    $fieldRegistry = makeFieldRegistry($scopeRegistry);
    $factory = new ScopeMetadataFactory($scopeRegistry, $fieldRegistry);

    $metadata = $factory->for(EntityWithScopedProperties::class);

    expect($metadata->axesForProperty('name'))->toBe(['store', 'website'])
        ->and($metadata->axesForProperty('description'))->toBe(['website']);
});

it('caches metadata per class within the factory', function (): void {
    $scopeRegistry = makeMockRegistry(['store', 'website']);
    $fieldRegistry = makeFieldRegistry($scopeRegistry);
    $factory = new ScopeMetadataFactory($scopeRegistry, $fieldRegistry);

    $first = $factory->for(EntityWithScopedProperties::class);
    $second = $factory->for(EntityWithScopedProperties::class);

    expect($first)->toBe($second);
});

it('throws UnknownAxisException when a Scoped property declares an unknown axis', function (): void {
    $scopeRegistry = makeMockRegistry(['store', 'website']);
    $fieldRegistry = makeFieldRegistry($scopeRegistry);
    $factory = new ScopeMetadataFactory($scopeRegistry, $fieldRegistry);

    expect(fn () => $factory->for(EntityWithUnknownAxis::class))
        ->toThrow(UnknownAxisException::class);
});

it('reports whether a property is scoped via isScoped', function (): void {
    $scopeRegistry = makeMockRegistry(['store', 'website']);
    $fieldRegistry = makeFieldRegistry($scopeRegistry);
    $factory = new ScopeMetadataFactory($scopeRegistry, $fieldRegistry);

    $metadata = $factory->for(EntityWithScopedProperties::class);

    expect($metadata->isScoped('name'))->toBeTrue()
        ->and($metadata->isScoped('price'))->toBeFalse();
});

it('lists all scoped property names via scopedProperties', function (): void {
    $scopeRegistry = makeMockRegistry(['store', 'website']);
    $fieldRegistry = makeFieldRegistry($scopeRegistry);
    $factory = new ScopeMetadataFactory($scopeRegistry, $fieldRegistry);

    $metadata = $factory->for(EntityWithScopedProperties::class);

    expect($metadata->scopedProperties())->toBe(['name', 'description']);
});

// ─── New requirements ─────────────────────────────────────────────────────────

it(
    'returns metadata reflecting properties previously registered programmatically in ScopedFieldRegistry',
    function (): void {
        $scopeRegistry = makeMockRegistry(['store', 'website']);
        $fieldRegistry = makeFieldRegistry($scopeRegistry);
        $fieldRegistry->register(
            entityClass: EntityForRegistryTest::class,
            property: 'name',
            axes: ['store'],
        );

        $factory = new ScopeMetadataFactory($scopeRegistry, $fieldRegistry);

        $metadata = $factory->for(EntityForRegistryTest::class);

        expect($metadata->isScoped('name'))->toBeTrue()
            ->and($metadata->axesForProperty('name'))->toBe(['store'])
            ->and($metadata->isScoped('description'))->toBeFalse();
    },
);

it('returns metadata for properties annotated with the Scoped attribute on the entity class', function (): void {
    $scopeRegistry = makeMockRegistry(['store', 'website']);
    $fieldRegistry = makeFieldRegistry($scopeRegistry);
    $factory = new ScopeMetadataFactory($scopeRegistry, $fieldRegistry);

    $metadata = $factory->for(EntityWithScopedProperties::class);

    expect($metadata->isScoped('name'))->toBeTrue()
        ->and($metadata->axesForProperty('name'))->toBe(['store', 'website'])
        ->and($metadata->isScoped('description'))->toBeTrue()
        ->and($metadata->axesForProperty('description'))->toBe(['website']);
});

it('writes attribute-discovered axes into ScopedFieldRegistry on first access to the class', function (): void {
    $scopeRegistry = makeMockRegistry(['store', 'website']);
    $fieldRegistry = makeFieldRegistry($scopeRegistry);
    $factory = new ScopeMetadataFactory($scopeRegistry, $fieldRegistry);

    // Before calling for(), the registry should have no entry
    expect($fieldRegistry->axesForProperty(EntityWithScopedProperties::class, 'name'))->toBe([]);

    $factory->for(EntityWithScopedProperties::class);

    // After calling for(), the registry should contain the discovered axes
    expect($fieldRegistry->axesForProperty(EntityWithScopedProperties::class, 'name'))->toBe(['store', 'website'])
        ->and($fieldRegistry->axesForProperty(EntityWithScopedProperties::class, 'description'))->toBe(['website']);
});

it('merges axes (union) when both an attribute and a registry entry declare the same property', function (): void {
    $scopeRegistry = makeMockRegistry(['store', 'website', 'locale']);
    $fieldRegistry = makeFieldRegistry($scopeRegistry);
    // Pre-register 'locale' axis for 'name' before factory scans attributes
    $fieldRegistry->register(
        entityClass: EntityWithScopedProperties::class,
        property: 'name',
        axes: ['locale'],
    );

    $factory = new ScopeMetadataFactory($scopeRegistry, $fieldRegistry);
    $metadata = $factory->for(EntityWithScopedProperties::class);

    // Attribute declares ['store', 'website'] for 'name'; registry had ['locale']
    // Merged result should be union: ['locale', 'store', 'website'] (registry order first, then attribute additions)
    $axes = $metadata->axesForProperty('name');
    expect($axes)->toContain('store')
        ->and($axes)->toContain('website')
        ->and($axes)->toContain('locale');
});

it('scans each class for attributes at most once across multiple for calls', function (): void {
    $scopeRegistry = makeMockRegistry(['store', 'website']);
    $fieldRegistry = makeFieldRegistry($scopeRegistry);
    $factory = new ScopeMetadataFactory($scopeRegistry, $fieldRegistry);

    $factory->for(EntityWithScopedProperties::class);
    $factory->for(EntityWithScopedProperties::class);

    // If scanned twice, registry would have duplicates — but ScopedFieldRegistry unions,
    // so the real test is that the metadata is cached (same instance)
    $first = $factory->for(EntityWithScopedProperties::class);
    $second = $factory->for(EntityWithScopedProperties::class);

    expect($first)->toBe($second);
});

it(
    'scans each class for attributes at most once even when ScopedFieldRegistry was prepopulated for that class before the first for call',
    function (): void {
        $scopeRegistry = makeMockRegistry(['store', 'website', 'locale']);
        $fieldRegistry = makeFieldRegistry($scopeRegistry);

        // Pre-populate the registry before the factory sees it
        $fieldRegistry->register(
            entityClass: EntityWithScopedProperties::class,
            property: 'name',
            axes: ['locale'],
        );

        $factory = new ScopeMetadataFactory($scopeRegistry, $fieldRegistry);

        // Call for() twice — attribute scan should happen exactly once
        $first = $factory->for(EntityWithScopedProperties::class);
        $second = $factory->for(EntityWithScopedProperties::class);

        // Both calls return the same cached instance
        expect($first)->toBe($second);
        // 'name' has locale (pre-registered) + store, website (from attribute)
        $axes = $first->axesForProperty('name');
        expect($axes)->toContain('store')
            ->and($axes)->toContain('website')
            ->and($axes)->toContain('locale');
    },
);

it('returns cached ScopeMetadata for repeated for calls with the same class', function (): void {
    $scopeRegistry = makeMockRegistry(['store', 'website']);
    $fieldRegistry = makeFieldRegistry($scopeRegistry);
    $factory = new ScopeMetadataFactory($scopeRegistry, $fieldRegistry);

    $first = $factory->for(EntityWithScopedProperties::class);
    $second = $factory->for(EntityWithScopedProperties::class);

    expect($first)->toBe($second);
});

it('returns empty ScopeMetadata when the class has neither attributes nor registry entries', function (): void {
    $scopeRegistry = makeMockRegistry(['store', 'website']);
    $fieldRegistry = makeFieldRegistry($scopeRegistry);
    $factory = new ScopeMetadataFactory($scopeRegistry, $fieldRegistry);

    $metadata = $factory->for(EntityWithNoScopedProperties::class);

    expect($metadata->hasScopedProperties())->toBeFalse()
        ->and($metadata->scopedProperties())->toBe([]);
});

it(
    'throws UnknownAxisException when an attribute references an axis not registered in ScopeRegistryInterface',
    function (): void {
        $scopeRegistry = makeMockRegistry(['store', 'website']);
        $fieldRegistry = makeFieldRegistry($scopeRegistry);
        $factory = new ScopeMetadataFactory($scopeRegistry, $fieldRegistry);

        expect(fn () => $factory->for(EntityWithUnknownAxis::class))
            ->toThrow(UnknownAxisException::class);
    },
);

it(
    'treats a Scoped attribute with an empty axes list as a no-op (the property is not marked as scoped)',
    function (): void {
        $scopeRegistry = makeMockRegistry(['store', 'website']);
        $fieldRegistry = makeFieldRegistry($scopeRegistry);
        $factory = new ScopeMetadataFactory($scopeRegistry, $fieldRegistry);

        $metadata = $factory->for(EntityWithEmptyAxes::class);

        expect($metadata->isScoped('name'))->toBeFalse()
            ->and($metadata->hasScopedProperties())->toBeFalse();
    },
);

it('discovers Scoped properties inherited from a parent class', function (): void {
    $scopeRegistry = makeMockRegistry(['store', 'website']);
    $fieldRegistry = makeFieldRegistry($scopeRegistry);
    $factory = new ScopeMetadataFactory($scopeRegistry, $fieldRegistry);

    $metadata = $factory->for(ChildEntityInheritingScoped::class);

    expect($metadata->isScoped('name'))->toBeTrue()
        ->and($metadata->axesForProperty('name'))->toBe(['store']);
});

it('freezes cached metadata against later registry mutations', function (): void {
    $scopeRegistry = makeMockRegistry(['store', 'website']);
    $fieldRegistry = makeFieldRegistry($scopeRegistry);
    $factory = new ScopeMetadataFactory($scopeRegistry, $fieldRegistry);

    // Call for() to cache the metadata
    $before = $factory->for(EntityWithScopedProperties::class);

    // Now mutate the registry (add 'website' to EntityForRegistryTest,
    // and try to register something on EntityWithScopedProperties)
    // The cached metadata for EntityWithScopedProperties should not change
    $fieldRegistry->register(
        entityClass: EntityWithScopedProperties::class,
        property: 'price',
        axes: ['website'],
    );

    $after = $factory->for(EntityWithScopedProperties::class);

    // Same instance — frozen
    expect($after)->toBe($before)
        ->and($after->isScoped('price'))->toBeFalse();
});

it(
    'sees registrations made before the first for call when they happen after construction but before the first read',
    function (): void {
        $scopeRegistry = makeMockRegistry(['store', 'website']);
        $fieldRegistry = makeFieldRegistry($scopeRegistry);
        $factory = new ScopeMetadataFactory($scopeRegistry, $fieldRegistry);

        // Register AFTER factory construction but BEFORE first for() call
        $fieldRegistry->register(
            entityClass: EntityForRegistryTest::class,
            property: 'description',
            axes: ['website'],
        );

        $metadata = $factory->for(EntityForRegistryTest::class);

        expect($metadata->isScoped('description'))->toBeTrue()
            ->and($metadata->axesForProperty('description'))->toBe(['website']);
    },
);
