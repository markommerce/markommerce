<?php

declare(strict_types=1);

use Markommerce\Layout\Attributes\ProvidesHandles;
use Markommerce\Layout\Cache\ArtifactWriter;
use Markommerce\Layout\Cache\PreparedPlace;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Cache\PreparedTreeBuilder;
use Markommerce\Layout\Compiler\ResolvedLayout;
use Markommerce\Layout\Compiler\ResolvedPlace;
use Markommerce\Layout\Compiler\ValidationPhase;
use Markommerce\Layout\Contracts\HandleProvider;
use Markommerce\Layout\Exception\ChainedHandleProviderException;
use Markommerce\Layout\Exception\DuplicateContextTokenException;
use Markommerce\Layout\Exception\DynamicHandleConflictException;
use Markommerce\Layout\Provide;
use Markommerce\Layout\ProvideHandle;
use Markommerce\Layout\Runtime\TreeMerger;

// =============================================================================
// Fixtures: handle provider classes used in multiple tests
// =============================================================================

#[ProvidesHandles(['cross.dynamic.a', 'cross.dynamic.b'])]
class CrossStaticProvider implements HandleProvider
{
    public function provide(array $props): array
    {
        return ['cross.dynamic.a', 'cross.dynamic.b'];
    }
}

class CrossOpaqueProvider implements HandleProvider
{
    public function provide(array $props): array
    {
        return ['some.dynamic.handle'];
    }
}

// =============================================================================
// Helpers
// =============================================================================

function cross_makeLayout(
    string $handleKey,
    array $slots = [],
    array $context = [],
    array $handleProviders = [],
): ResolvedLayout {
    return new ResolvedLayout(
        handle: $handleKey,
        handleKey: $handleKey,
        template: null,
        slots: $slots,
        context: $context,
        handleProviders: $handleProviders,
    );
}

function cross_makePlace(?string $name = null, string $component = 'SomeComponent'): ResolvedPlace
{
    return new ResolvedPlace(
        component: $component,
        name: $name,
        props: [],
        slots: [],
    );
}

function cross_makePreparedTree(
    string $handleKey,
    array $slots = [],
    array $context = [],
    array $handleProviders = [],
    array $placementNames = [],
): PreparedTree {
    return new PreparedTree(
        handleKey: $handleKey,
        template: null,
        slots: $slots,
        context: $context,
        handleProviders: $handleProviders,
        placementNames: $placementNames,
    );
}

function cross_makePreparedPlace(?string $name = null): PreparedPlace
{
    return new PreparedPlace(
        component: 'SomeComponent',
        name: $name,
        props: [],
        slots: [],
        decorators: [],
        template: '',
    );
}

// =============================================================================
// Requirement 1: ProvidesHandles attribute
// =============================================================================

it('defines a #[ProvidesHandles(...)] PHP attribute that providers can use to declare their static return set', function (): void {
    $reflection = new ReflectionClass(ProvidesHandles::class);

    // Is a PHP Attribute
    $attrAttributes = $reflection->getAttributes(Attribute::class);
    expect($attrAttributes)->not->toBeEmpty();

    $attributeInstance = $attrAttributes[0]->newInstance();
    expect($attributeInstance->flags & Attribute::TARGET_CLASS)->not->toBe(0);

    // Has a public $handles property
    expect($reflection->hasProperty('handles'))->toBeTrue();
    $handlesProp = $reflection->getProperty('handles');
    expect($handlesProp->isPublic())->toBeTrue();

    // Can annotate a class and its values are accessible via reflection
    $providerReflection = new ReflectionClass(CrossStaticProvider::class);
    $attrs = $providerReflection->getAttributes(ProvidesHandles::class);
    expect($attrs)->toHaveCount(1);

    /** @var ProvidesHandles $instance */
    $instance = $attrs[0]->newInstance();
    expect($instance->handles)->toBe(['cross.dynamic.a', 'cross.dynamic.b']);
});

// =============================================================================
// Requirement 2: compile-time DynamicHandleConflictException for placement conflict
// =============================================================================

it('throws DynamicHandleConflictException at compile time when a statically-known dynamic handle declares a placement that conflicts with the base handle', function (): void {
    $dynamicALayout = cross_makeLayout(
        handleKey: 'cross.dynamic.a',
        slots: [
            'main' => [cross_makePlace('shared.placement')],
        ],
    );

    $baseLayout = cross_makeLayout(
        handleKey: 'base.handle',
        slots: [
            'main' => [cross_makePlace('shared.placement')],  // same name as dynamic
        ],
        handleProviders: [
            new ProvideHandle(provider: CrossStaticProvider::class, props: []),
        ],
    );

    $resolvedLayouts = [
        'cross.dynamic.a' => $dynamicALayout,
        'cross.dynamic.b' => cross_makeLayout(handleKey: 'cross.dynamic.b'),
        'base.handle' => $baseLayout,
    ];

    $validator = new ValidationPhase();
    expect(fn() => $validator->validate($resolvedLayouts))
        ->toThrow(DynamicHandleConflictException::class);
});

// =============================================================================
// Requirement 3: runtime DynamicHandleConflictException for opaque provider
// =============================================================================

it('throws DynamicHandleConflictException at runtime when an opaque provider returns a handle whose tree conflicts with the base', function (): void {
    $base = cross_makePreparedTree(
        handleKey: 'base.handle',
        slots: [
            'main' => [cross_makePreparedPlace('shared.placement')],
        ],
        placementNames: ['shared.placement'],
    );

    $addition = cross_makePreparedTree(
        handleKey: 'opaque.dynamic',
        slots: [
            'main' => [cross_makePreparedPlace('shared.placement')],  // conflict
        ],
    );

    $merger = new TreeMerger();
    expect(fn() => $merger->merge($base, [$addition]))
        ->toThrow(DynamicHandleConflictException::class);
});

// =============================================================================
// Requirement 4: compile-time DuplicateContextTokenException
// =============================================================================

it('throws DuplicateContextTokenException at compile time when a statically-known dynamic handle and the base handle share a context token', function (): void {
    $dynamicALayout = cross_makeLayout(
        handleKey: 'cross.dynamic.a',
        context: [new Provide('shared.token', 'SomeProvider', [])],
    );

    $baseLayout = cross_makeLayout(
        handleKey: 'base.handle',
        slots: [],
        context: [new Provide('shared.token', 'AnotherProvider', [])],  // same token
        handleProviders: [
            new ProvideHandle(provider: CrossStaticProvider::class, props: []),
        ],
    );

    $resolvedLayouts = [
        'cross.dynamic.a' => $dynamicALayout,
        'cross.dynamic.b' => cross_makeLayout(handleKey: 'cross.dynamic.b'),
        'base.handle' => $baseLayout,
    ];

    $validator = new ValidationPhase();
    expect(fn() => $validator->validate($resolvedLayouts))
        ->toThrow(DuplicateContextTokenException::class);
});

// =============================================================================
// Requirement 5: placementNames field in PreparedTree for runtime detection
// =============================================================================

it('records the base tree\'s placement-name set in the artifact for runtime conflict detection', function (): void {
    $tree = cross_makePreparedTree(
        handleKey: 'base.handle',
        slots: [],
        placementNames: ['my.placement', 'another.placement'],
    );

    expect($tree->placementNames)->toBe(['my.placement', 'another.placement']);

    // Verify it round-trips through PhpCodeEmitter / ArtifactWriter
    $path = sys_get_temp_dir() . '/test_placement_names_' . uniqid() . '.php';
    $writer = new ArtifactWriter($path);
    $writer->write(['base.handle' => $tree]);

    $loaded = require $path;
    expect($loaded['base.handle']->placementNames)->toBe(['my.placement', 'another.placement']);

    @unlink($path);
});

// =============================================================================
// Requirement 6: passes validation when disjoint placement names
// =============================================================================

it('passes validation when dynamic and base trees declare disjoint placement names', function (): void {
    $dynamicALayout = cross_makeLayout(
        handleKey: 'cross.dynamic.a',
        slots: [
            'main' => [cross_makePlace('dynamic.placement')],
        ],
    );

    $baseLayout = cross_makeLayout(
        handleKey: 'base.handle',
        slots: [
            'main' => [cross_makePlace('base.placement')],
        ],
        handleProviders: [
            new ProvideHandle(provider: CrossStaticProvider::class, props: []),
        ],
    );

    $resolvedLayouts = [
        'cross.dynamic.a' => $dynamicALayout,
        'cross.dynamic.b' => cross_makeLayout(handleKey: 'cross.dynamic.b'),
        'base.handle' => $baseLayout,
    ];

    $validator = new ValidationPhase();
    expect(fn() => $validator->validate($resolvedLayouts))->not->toThrow(\Throwable::class);
});

// =============================================================================
// Requirement 7: compile-time ChainedHandleProviderException
// =============================================================================

it('throws ChainedHandleProviderException at compile time when a statically-known dynamic handle\'s tree declares its own handleProviders', function (): void {
    // cross.dynamic.a itself has handleProviders — not allowed
    $dynamicALayout = cross_makeLayout(
        handleKey: 'cross.dynamic.a',
        handleProviders: [
            new ProvideHandle(provider: CrossOpaqueProvider::class, props: []),
        ],
    );

    $baseLayout = cross_makeLayout(
        handleKey: 'base.handle',
        handleProviders: [
            new ProvideHandle(provider: CrossStaticProvider::class, props: []),
        ],
    );

    $resolvedLayouts = [
        'cross.dynamic.a' => $dynamicALayout,
        'cross.dynamic.b' => cross_makeLayout(handleKey: 'cross.dynamic.b'),
        'base.handle' => $baseLayout,
    ];

    $validator = new ValidationPhase();
    expect(fn() => $validator->validate($resolvedLayouts))
        ->toThrow(ChainedHandleProviderException::class);
});

// =============================================================================
// Requirement 8: runtime ChainedHandleProviderException for opaque provider
// =============================================================================

it('throws ChainedHandleProviderException at runtime when an opaque provider returns a handle whose tree declares handleProviders', function (): void {
    $base = cross_makePreparedTree(handleKey: 'base.handle');

    $addition = cross_makePreparedTree(
        handleKey: 'opaque.dynamic',
        handleProviders: [
            new ProvideHandle(provider: CrossOpaqueProvider::class, props: []),
        ],
    );

    $merger = new TreeMerger();
    expect(fn() => $merger->merge($base, [$addition]))
        ->toThrow(ChainedHandleProviderException::class);
});
