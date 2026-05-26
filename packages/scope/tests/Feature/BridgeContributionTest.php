<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Module\DependencyResolver;
use Marko\Core\Module\ModuleManifest;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Exceptions\UnknownEntityClassException;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Tests\Support\MixedEntity;
use Markommerce\Scope\Tests\Support\PlainEntity;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Build a real Container wired with the scope module's bindings/singletons,
 * with a custom config that defines the given axes. Boot is NOT run here.
 *
 * @param list<string> $axisNames
 */
function buildScopeContainerWithAxes(array $axisNames): Container
{
    $axesConfig = [];
    foreach ($axisNames as $name) {
        $axesConfig[$name] = ['default' => 'default', 'scopes' => ['default' => []]];
    }

    $config = new ConfigRepository(['scope' => ['axes' => $axesConfig]]);

    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);

    $module = require dirname(__DIR__, 2) . '/module.php';

    foreach ($module['singletons'] as $singleton) {
        $container->singleton($singleton);
    }

    foreach ($module['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    return $container;
}

/**
 * Build a ModuleManifest for the scope module (simulates the real package
 * discovered from disk, but as an inline value object).
 */
function scopeModuleManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/scope',
        version: '1.0.0',
    );
}

/**
 * Run boot closures for an ordered list of manifests against a container,
 * mirroring the loop in Application::initialize() (lines 179-185).
 *
 * @param ModuleManifest[] $ordered
 */
function runBootLoop(array $ordered, ContainerInterface $container): void
{
    foreach ($ordered as $module) {
        if ($module->boot !== null) {
            $container->call($module->boot);
        }
    }
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it(
    'it allows a synthetic bridge module\'s boot closure to register a property via ScopedFieldRegistry',
    function (): void {
        $container = buildScopeContainerWithAxes(['locale']);
    
        $bridge = new ModuleManifest(
            name: 'acme/bridge',
            version: '1.0.0',
            require: ['markommerce/scope' => '*'],
            boot: function (ScopedFieldRegistry $scopedFieldRegistry): void {
                $scopedFieldRegistry->register(
                    entityClass: PlainEntity::class,
                    property: 'title',
                    axes: ['locale'],
                );
            },
        );
    
        $resolver = new DependencyResolver();
        $ordered = $resolver->resolve([scopeModuleManifest(), $bridge]);
    
        runBootLoop($ordered, $container);
    
        $registry = $container->get(ScopedFieldRegistry::class);
        expect($registry->axesForProperty(PlainEntity::class, 'title'))->toBe(['locale']);
    }
);

it(
    'it exposes programmatically-registered properties through ScopeMetadataFactory after the bridge boot completes',
    function (): void {
        $container = buildScopeContainerWithAxes(['locale']);
    
        $bridge = new ModuleManifest(
            name: 'acme/bridge',
            version: '1.0.0',
            require: ['markommerce/scope' => '*'],
            boot: function (ScopedFieldRegistry $scopedFieldRegistry): void {
                $scopedFieldRegistry->register(
                    entityClass: PlainEntity::class,
                    property: 'summary',
                    axes: ['locale'],
                );
            },
        );
    
        $resolver = new DependencyResolver();
        $ordered = $resolver->resolve([scopeModuleManifest(), $bridge]);
    
        runBootLoop($ordered, $container);
    
        $factory = $container->get(ScopeMetadataFactory::class);
        $metadata = $factory->for(PlainEntity::class);
    
        expect($metadata->isScoped('summary'))->toBeTrue()
            ->and($metadata->axesForProperty('summary'))->toBe(['locale'])
            ->and($metadata->isScoped('position'))->toBeFalse();
    }
);

it(
    'it unions axes from a boot-time registration with axes discovered from a Scoped attribute on the same property',
    function (): void {
        $container = buildScopeContainerWithAxes(['locale', 'market']);
    
        $bridge = new ModuleManifest(
            name: 'acme/bridge',
            version: '1.0.0',
            require: ['markommerce/scope' => '*'],
            boot: function (ScopedFieldRegistry $scopedFieldRegistry): void {
                // Register 'title' under 'market' — the class already has #[Scoped(axes: ['locale'])] on it
            $scopedFieldRegistry->register(
                    entityClass: MixedEntity::class,
                    property: 'title',
                    axes: ['market'],
                );
            },
        );
    
        $resolver = new DependencyResolver();
        $ordered = $resolver->resolve([scopeModuleManifest(), $bridge]);
    
        runBootLoop($ordered, $container);
    
        $factory = $container->get(ScopeMetadataFactory::class);
        $metadata = $factory->for(MixedEntity::class);
    
        $axes = $metadata->axesForProperty('title');
        expect($axes)->toContain('locale')
            ->and($axes)->toContain('market');
    }
);

it(
    'it throws UnknownAxisException at boot when a registration references an axis that is not in ScopeRegistryInterface',
    function (): void {
        $container = buildScopeContainerWithAxes(['locale']);
    
        $bridge = new ModuleManifest(
            name: 'acme/bridge',
            version: '1.0.0',
            require: ['markommerce/scope' => '*'],
            boot: function (ScopedFieldRegistry $scopedFieldRegistry): void {
                $scopedFieldRegistry->register(
                    entityClass: PlainEntity::class,
                    property: 'title',
                    axes: ['nonexistent'],
                );
            },
        );
    
        $resolver = new DependencyResolver();
        $ordered = $resolver->resolve([scopeModuleManifest(), $bridge]);
    
        expect(fn () => runBootLoop($ordered, $container))
            ->toThrow(UnknownAxisException::class);
    }
);

it(
    'it throws UnknownEntityClassException at boot when a bridge registers against a class name that does not exist (simulates a typo in module.php)',
    function (): void {
        $container = buildScopeContainerWithAxes(['locale']);
    
        $bridge = new ModuleManifest(
            name: 'acme/bridge',
            version: '1.0.0',
            require: ['markommerce/scope' => '*'],
            boot: function (ScopedFieldRegistry $scopedFieldRegistry): void {
                $scopedFieldRegistry->register(
                    entityClass: 'Acme\\NonExistent\\TypoClass',
                    property: 'title',
                    axes: ['locale'],
                );
            },
        );
    
        $resolver = new DependencyResolver();
        $ordered = $resolver->resolve([scopeModuleManifest(), $bridge]);
    
        expect(fn () => runBootLoop($ordered, $container))
            ->toThrow(UnknownEntityClassException::class);
    }
);

it('it preserves boot-time registrations across multiple for calls on the same class', function (): void {
    $container = buildScopeContainerWithAxes(['locale', 'market']);

    $bridge = new ModuleManifest(
        name: 'acme/bridge',
        version: '1.0.0',
        require: ['markommerce/scope' => '*'],
        boot: function (ScopedFieldRegistry $scopedFieldRegistry): void {
            $scopedFieldRegistry->register(
                entityClass: PlainEntity::class,
                property: 'title',
                axes: ['locale'],
            );
            $scopedFieldRegistry->register(
                entityClass: PlainEntity::class,
                property: 'summary',
                axes: ['market'],
            );
        },
    );

    $resolver = new DependencyResolver();
    $ordered = $resolver->resolve([scopeModuleManifest(), $bridge]);

    runBootLoop($ordered, $container);

    $factory = $container->get(ScopeMetadataFactory::class);

    // Call for() multiple times — each call must return same cached instance
    $first = $factory->for(PlainEntity::class);
    $second = $factory->for(PlainEntity::class);
    $third = $factory->for(PlainEntity::class);

    expect($first)->toBe($second)
        ->and($second)->toBe($third)
        ->and($first->isScoped('title'))->toBeTrue()
        ->and($first->axesForProperty('title'))->toBe(['locale'])
        ->and($first->isScoped('summary'))->toBeTrue()
        ->and($first->axesForProperty('summary'))->toBe(['market']);
});

it(
    'it sorts a synthetic bridge after scope in DependencyResolver when the bridge declares markommerce/scope as a require, so the bridge\'s boot runs with axes available',
    function (): void {
        $bridge = new ModuleManifest(
            name: 'acme/bridge',
            version: '1.0.0',
            require: ['markommerce/scope' => '*'],
            boot: function (): void {},
        );
    
        $resolver = new DependencyResolver();
        // Pass bridge first, scope second — resolver must reorder them
    $ordered = $resolver->resolve([$bridge, scopeModuleManifest()]);
    
        $names = array_map(fn (ModuleManifest $m) => $m->name, $ordered);
    
        $scopePosition = array_search('markommerce/scope', $names, true);
        $bridgePosition = array_search('acme/bridge', $names, true);
    
        expect($scopePosition)->toBeInt()
            ->and($bridgePosition)->toBeInt();
    
        /** @var int $scopePosition */
        /** @var int $bridgePosition */
        expect($scopePosition)->toBeLessThan($bridgePosition);
    }
);

it(
    'it auto-injects ScopedFieldRegistry into a bridge boot closure that type-hints it directly (verifies container call() behaviour bridges in P2 will rely on)',
    function (): void {
        $container = buildScopeContainerWithAxes(['locale']);
    
        $injectedRegistry = null;
    
        $bridge = new ModuleManifest(
            name: 'acme/bridge',
            version: '1.0.0',
            require: ['markommerce/scope' => '*'],
            boot: function (ScopedFieldRegistry $scopedFieldRegistry) use (&$injectedRegistry): void {
                $injectedRegistry = $scopedFieldRegistry;
                $scopedFieldRegistry->register(
                    entityClass: PlainEntity::class,
                    property: 'title',
                    axes: ['locale'],
                );
            },
        );
    
        $resolver = new DependencyResolver();
        $ordered = $resolver->resolve([scopeModuleManifest(), $bridge]);
    
        runBootLoop($ordered, $container);
    
        $expectedRegistry = $container->get(ScopedFieldRegistry::class);
    
        expect($injectedRegistry)->not->toBeNull()
            ->and($injectedRegistry)->toBe($expectedRegistry);
    
        /** @var ScopedFieldRegistry $injectedRegistry */
        expect($injectedRegistry->axesForProperty(PlainEntity::class, 'title'))->toBe(['locale']);
    }
);
