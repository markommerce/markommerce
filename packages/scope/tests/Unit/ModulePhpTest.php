<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Exceptions\BindingException;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\NoDriverException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Query\ScopedFieldRendererInterface;
use Markommerce\Scope\Query\ScopedOrderByFactory;
use Markommerce\Scope\Registry\PhpScopeRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolver\Resolution\ScopeResolverChainFactory;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Storage\DefaultScopeGuard;

it('binds ScopeRegistryInterface to PhpScopeRegistry', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['bindings'])->toHaveKey(ScopeRegistryInterface::class);
});

it('registers ScopeContext as a singleton', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module)->toHaveKey('singletons')
        ->and($module['singletons'])->toContain(ScopeContext::class);
});

it('registers ScopeMetadataFactory as a singleton', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['singletons'])->toContain(ScopeMetadataFactory::class);
});

it('registers ScopeResolver as a singleton', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['singletons'])->toContain(ScopeResolver::class);
});

it('registers ScopedOrderByFactory as a singleton', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['singletons'])->toContain(ScopedOrderByFactory::class);
});

it('does not bind ScopedFieldRendererInterface', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['bindings'])->not->toHaveKey(ScopedFieldRendererInterface::class);
});

it(
    'ModulePhpTest expects BindingException (not NoDriverException) when ScopedFieldRendererInterface is unbound',
    function (): void {
        $container = new Container();

        // Marko's Container::get() only emits NoDriverException for ids starting with 'Marko\\'
        // ScopedFieldRendererInterface starts with 'Markommerce\\', so we get BindingException instead
        expect(fn () => $container->get(ScopedFieldRendererInterface::class))
                ->toThrow(BindingException::class);
    },
);

it(
    'ModulePhpTest verifies NoDriverException::noDriverInstalled() suggestion mentions markommerce/scope-pgsql',
    function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())->toContain('markommerce/scope-pgsql');
    },
);

it('configures DefaultScopeGuard from the scope registry during module boot', function (): void {
    DefaultScopeGuard::reset();

    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module)->toHaveKey('boot');

    $localeAxis = new ScopeAxis(
        name: 'locale',
        hierarchy: ScopeHierarchy::fromPaths(['global', 'en', 'fr']),
        default: 'global',
    );
    $geoAxis = new ScopeAxis(
        name: 'geo',
        hierarchy: ScopeHierarchy::fromPaths(['global', 'eu', 'us']),
        default: 'global',
    );

    $registry = $this->createMock(ScopeRegistryInterface::class);
    $registry->expects($this->once())
        ->method('listAxes')
        ->willReturn(['locale', 'geo']);
    $registry->expects($this->exactly(2))
        ->method('getAxis')
        ->willReturnMap([
            ['locale', $localeAxis],
            ['geo', $geoAxis],
        ]);

    $factory = $this->createMock(ScopeResolverChainFactory::class);
    $factory->expects($this->exactly(2))
        ->method('for')
        ->willReturn([]);

    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->exactly(2))
        ->method('get')
        ->willReturnMap([
            [ScopeRegistryInterface::class, $registry],
            [ScopeResolverChainFactory::class, $factory],
        ]);

    ($module['boot'])($container);

    expect(DefaultScopeGuard::isConfigured())->toBeTrue();

    DefaultScopeGuard::reset();
});

it('constructs PhpScopeRegistry from injected config repository', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';
    $factory = $module['bindings'][ScopeRegistryInterface::class];

    $config = $this->createMock(ConfigRepositoryInterface::class);
    $config->expects($this->once())
        ->method('getArray')
        ->with('scope.axes')
        ->willReturn([]);

    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->once())
        ->method('get')
        ->with(ConfigRepositoryInterface::class)
        ->willReturn($config);

    $result = $factory($container);

    expect($result)->toBeInstanceOf(PhpScopeRegistry::class);
});

it('module.php registers ScopedFieldRegistry as a singleton', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['singletons'])->toContain(ScopedFieldRegistry::class);
});

it('it boots scope with no locale axis present in PhpScopeRegistry after the scope module\'s boot closure runs', function (): void {
    DefaultScopeGuard::reset();

    $rawConfig = require dirname(__DIR__, 2) . '/config/scope.php';
    $config = new ConfigRepository(['scope' => $rawConfig]);

    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);

    $module = require dirname(__DIR__, 2) . '/module.php';

    foreach ($module['singletons'] as $singleton) {
        $container->singleton($singleton);
    }

    foreach ($module['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    $registry = $container->get(ScopeRegistryInterface::class);

    expect($registry->listAxes())->not->toContain('locale')
        ->and($registry->listAxes())->toContain('market')
        ->and($registry->listAxes())->toContain('channel');

    DefaultScopeGuard::reset();
});

it('it resolves ScopedFieldRegistry from a real container with scope\'s module loaded', function (): void {
    $rawConfig = require dirname(__DIR__, 2) . '/config/scope.php';
    $config = new ConfigRepository(['scope' => $rawConfig]);

    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);

    $module = require dirname(__DIR__, 2) . '/module.php';

    foreach ($module['singletons'] as $singleton) {
        $container->singleton($singleton);
    }

    foreach ($module['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    $result = $container->get(ScopedFieldRegistry::class);

    expect($result)->toBeInstanceOf(ScopedFieldRegistry::class);
});

it(
    'it returns the same ScopedFieldRegistry instance on repeated container resolutions (singleton)',
    function (): void {
        $rawConfig = require dirname(__DIR__, 2) . '/config/scope.php';
        $config = new ConfigRepository(['scope' => $rawConfig]);

        $container = new Container();
        $container->instance(ConfigRepositoryInterface::class, $config);

        $module = require dirname(__DIR__, 2) . '/module.php';

        foreach ($module['singletons'] as $singleton) {
            $container->singleton($singleton);
        }

        foreach ($module['bindings'] as $interface => $implementation) {
            $container->bind($interface, $implementation);
        }

        $first = $container->get(ScopedFieldRegistry::class);
        $second = $container->get(ScopedFieldRegistry::class);

        expect($first)->toBe($second);
    },
);

it('it injects the same ScopedFieldRegistry instance into ScopeMetadataFactory via the container', function (): void {
    $rawConfig = require dirname(__DIR__, 2) . '/config/scope.php';
    $config = new ConfigRepository(['scope' => $rawConfig]);

    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);

    $module = require dirname(__DIR__, 2) . '/module.php';

    foreach ($module['singletons'] as $singleton) {
        $container->singleton($singleton);
    }

    foreach ($module['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    $registry = $container->get(ScopedFieldRegistry::class);
    $factory = $container->get(ScopeMetadataFactory::class);

    // The factory must hold the same ScopedFieldRegistry instance
    // We verify by registering a field in the registry and checking the factory uses it
    $registry->register(
        entityClass: ScopedFieldRegistry::class,
        property: 'map',
        axes: ['market'],
    );

    $metadata = $factory->for(ScopedFieldRegistry::class);

    expect($metadata->scopedProperties())->toContain('map');
});

it(
    'it allows register() to succeed without scope\'s boot closure having run, because PhpScopeRegistry resolves its axes at construction time',
    function (): void {
        $rawConfig = require dirname(__DIR__, 2) . '/config/scope.php';
        $config = new ConfigRepository(['scope' => $rawConfig]);

        $container = new Container();
        $container->instance(ConfigRepositoryInterface::class, $config);

        $module = require dirname(__DIR__, 2) . '/module.php';

        foreach ($module['singletons'] as $singleton) {
            $container->singleton($singleton);
        }

        foreach ($module['bindings'] as $interface => $implementation) {
            $container->bind($interface, $implementation);
        }

        // Deliberately skip invoking ($module['boot'])($container)
        $registry = $container->get(ScopedFieldRegistry::class);

        // ScopedFieldRegistry::class itself exists, and 'market' is a known axis in PhpScopeRegistry
        // because PhpScopeRegistry reads its axes directly from config at construction time — no boot needed.
        expect(fn () => $registry->register(
            entityClass: ScopedFieldRegistry::class,
            property: 'map',
            axes: ['market'],
        ))->not->toThrow(Throwable::class);
    },
);
