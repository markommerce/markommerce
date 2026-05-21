<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Exceptions\BindingException;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\NoDriverException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
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
