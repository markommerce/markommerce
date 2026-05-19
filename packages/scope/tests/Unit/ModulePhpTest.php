<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Exceptions\BindingException;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\NoDriverException;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Query\ScopedOrderByFactory;
use Markommerce\Scope\Query\ScopeSortRendererInterface;
use Markommerce\Scope\Registry\PhpScopeRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolver\ScopeResolver;

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

it('does not bind ScopeSortRendererInterface', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['bindings'])->not->toHaveKey(ScopeSortRendererInterface::class);
});

it(
    'ModulePhpTest expects BindingException (not NoDriverException) when ScopeSortRendererInterface is unbound',
    function (): void {
        $container = new Container();

        // Marko's Container::get() only emits NoDriverException for ids starting with 'Marko\\'
        // ScopeSortRendererInterface starts with 'Markommerce\\', so we get BindingException instead
        expect(fn () => $container->get(ScopeSortRendererInterface::class))
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
