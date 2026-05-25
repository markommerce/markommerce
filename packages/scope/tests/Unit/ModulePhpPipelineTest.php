<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Core\Container\ContainerInterface;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\InvalidResolverConfigException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Middleware\ScopeResolutionMiddleware;
use Markommerce\Scope\Registry\PhpScopeRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolver\Resolution\Builtin\StaticResolver;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionPipeline;
use Markommerce\Scope\Resolver\Resolution\ScopeResolverChainFactory;
use Markommerce\Scope\Storage\DefaultScopeGuard;

it('module.php registers ScopeResolverChainFactory as a singleton', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module)->toHaveKey('singletons')
        ->and($module['singletons'])->toContain(ScopeResolverChainFactory::class);
});

it(
    'module.php registers ScopeResolutionPipeline via a closure binding that handles a missing logger gracefully',
    function (): void {
        $module = require dirname(__DIR__, 2) . '/module.php';
    
        expect($module)->toHaveKey('bindings')
            ->and($module['bindings'])->toHaveKey(ScopeResolutionPipeline::class)
            ->and($module['bindings'][ScopeResolutionPipeline::class])->toBeInstanceOf(Closure::class);
    }
);

it('module.php registers ScopeResolutionPipeline as shared so the same instance is returned', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['singletons'])->toContain(ScopeResolutionPipeline::class);
});

it('module.php registers ScopeResolutionMiddleware as a singleton', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['singletons'])->toContain(ScopeResolutionMiddleware::class);
});

it('module.php declares ScopeResolutionMiddleware as a globalMiddleware entry with priority 5', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module)->toHaveKey('globalMiddleware');

    $entries = $module['globalMiddleware'];
    $found = array_find($entries, fn (array $entry): bool => $entry['class'] === ScopeResolutionMiddleware::class);

    expect($found)->not->toBeNull()
        ->and($found['priority'])->toBe(5);
});

it(
    'boot closure surfaces InvalidResolverConfigException from a misconfigured axis during Application initialize',
    function (): void {
        DefaultScopeGuard::reset();
    
        $module = require dirname(__DIR__, 2) . '/module.php';
    
        $localeAxis = new ScopeAxis(
            name: 'locale',
            hierarchy: ScopeHierarchy::fromPaths(['en']),
            default: 'en',
        );
    
        $registry = $this->createMock(ScopeRegistryInterface::class);
        $registry->method('listAxes')->willReturn(['locale']);
        $registry->method('getAxis')->willReturn($localeAxis);
    
        $factory = new class () extends ScopeResolverChainFactory
        {
            public function __construct()
            {
                // skip parent constructor
        }
    
            public function for(string $axisName): array
            {
                throw InvalidResolverConfigException::unknownClass('NoSuchClass', $axisName);
            }
        };
    
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function (string $id) use ($registry, $factory): object {
                return match ($id) {
                    ScopeRegistryInterface::class => $registry,
                    ScopeResolverChainFactory::class => $factory,
                    default => throw new RuntimeException("Unexpected: $id"),
                };
            });
    
        expect(fn () => ($module['boot'])($container))
            ->toThrow(InvalidResolverConfigException::class);
    
        DefaultScopeGuard::reset();
    }
);

it(
    'boot closure pre-builds every axis resolver chain so misconfig throws at boot not on first request',
    function (): void {
        DefaultScopeGuard::reset();
    
        $module = require dirname(__DIR__, 2) . '/module.php';
    
        $builtAxes = [];
    
        $localeAxis = new ScopeAxis(
            name: 'locale',
            hierarchy: ScopeHierarchy::fromPaths(['en', 'fr']),
            default: 'en',
        );
        $marketAxis = new ScopeAxis(
            name: 'market',
            hierarchy: ScopeHierarchy::fromPaths(['global', 'eu']),
            default: 'global',
        );
    
        $registry = $this->createMock(ScopeRegistryInterface::class);
        $registry->method('listAxes')->willReturn(['locale', 'market']);
        $registry->method('getAxis')->willReturnMap([
            ['locale', $localeAxis],
            ['market', $marketAxis],
        ]);
    
        $factory = new class ($builtAxes) extends ScopeResolverChainFactory
        {
            /** @param list<string> $builtAxes */
            public function __construct(private array &$builtAxes)
            {
                // skip parent constructor
        }
    
            public function for(string $axisName): array
            {
                $this->builtAxes[] = $axisName;
    
                return [];
            }
        };
    
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function (string $id) use ($registry, $factory): object {
                return match ($id) {
                    ScopeRegistryInterface::class => $registry,
                    ScopeResolverChainFactory::class => $factory,
                    default => throw new RuntimeException("Unexpected: $id"),
                };
            });
    
        ($module['boot'])($container);
    
        expect($builtAxes)->toContain('locale')
            ->and($builtAxes)->toContain('market');
    
        DefaultScopeGuard::reset();
    }
);

it('axis config with resolvers key successfully builds a chain via the factory after boot', function (): void {
    $rawConfig = [
        'axes' => [
            'locale' => [
                'default'   => 'en',
                'scopes'    => ['en' => [], 'pl' => []],
                'resolvers' => [
                    ['class' => StaticResolver::class, 'value' => 'en'],
                ],
            ],
        ],
    ];

    $config = new ConfigRepository(['scope' => $rawConfig]);
    $registry = new PhpScopeRegistry($config);

    $factory = new ScopeResolverChainFactory(
        new class () implements ContainerInterface
        {
            public function get(string $id): mixed
            {
                return new $id();
            }

            public function has(string $id): bool
            {
                return class_exists($id);
            }

            public function singleton(string $id): void {}

            public function instance(
                string $id,
                object $instance,
            ): void {}

            public function bind(
                string $id,
                mixed $implementation,
            ): void {}

            public function call(Closure $callable): mixed
            {
                return $callable();
            }
        },
        $config,
    );

    $chain = $factory->for('locale');

    expect($chain)->toHaveCount(1)
        ->and($chain[0])->toBeInstanceOf(StaticResolver::class);
});

it('existing config without resolvers key continues to load without error', function (): void {
    $rawConfig = require dirname(__DIR__, 2) . '/config/scope.php';
    $config = new ConfigRepository(['scope' => $rawConfig]);
    $registry = new PhpScopeRegistry($config);
    $factory = new ScopeResolverChainFactory(
        new class () implements ContainerInterface
        {
            public function get(string $id): mixed
            {
                return new $id();
            }

            public function has(string $id): bool
            {
                return class_exists($id);
            }

            public function singleton(string $id): void {}

            public function instance(
                string $id,
                object $instance,
            ): void {}

            public function bind(
                string $id,
                mixed $implementation,
            ): void {}

            public function call(Closure $callable): mixed
            {
                return $callable();
            }
        },
        $config,
    );

    foreach ($registry->listAxes() as $axisName) {
        $chain = $factory->for($axisName);
        expect($chain)->toBe([]);
    }
});

it(
    'the pipeline closure returns a working ScopeResolutionPipeline when marko log is not installed',
    function (): void {
        $module = require dirname(__DIR__, 2) . '/module.php';
    
        $rawConfig = require dirname(__DIR__, 2) . '/config/scope.php';
        $config = new ConfigRepository(['scope' => $rawConfig]);
    
        $registry = new PhpScopeRegistry($config);
        $context = new ScopeContext($registry);
        $factory = new ScopeResolverChainFactory(
            new class () implements ContainerInterface
            {
                public function get(string $id): mixed
                {
                    return new $id();
                }
    
                public function has(string $id): bool
                {
                    return class_exists($id);
                }
    
                public function singleton(string $id): void {}
    
                public function instance(
                    string $id,
                    object $instance,
                ): void {}
    
                public function bind(
                    string $id,
                    mixed $implementation,
                ): void {}
    
                public function call(Closure $callable): mixed
                {
                    return $callable();
                }
            },
            $config,
        );
    
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function (string $id) use ($registry, $context, $factory): object {
                return match ($id) {
                    ScopeRegistryInterface::class => $registry,
                    ScopeContext::class => $context,
                    ScopeResolverChainFactory::class => $factory,
                    default => throw new RuntimeException("Unexpected: $id"),
                };
            });
    
        $closure = $module['bindings'][ScopeResolutionPipeline::class];
        $pipeline = $closure($container);
    
        expect($pipeline)->toBeInstanceOf(ScopeResolutionPipeline::class);
    }
);
