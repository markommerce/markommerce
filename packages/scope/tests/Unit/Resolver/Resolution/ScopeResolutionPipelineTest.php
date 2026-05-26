<?php

declare(strict_types=1);

use Marko\Log\Contracts\LoggerInterface;
use Marko\Log\LogLevel;
use Marko\Routing\Http\Request;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolver\Resolution\ScopeAxisResolverInterface;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionPipeline;
use Markommerce\Scope\Resolver\Resolution\ScopeResolverChainFactory;

// ─── Fakes ───────────────────────────────────────────────────────────────────

/**
 * @param array<string, list<string>> $axes  axis-name => list of hierarchy paths
 * @param array<string, string>       $defaults  axis-name => default path
 * @param list<string>                $order  override registration order (defaults to axes keys)
 */
function makePipelineRegistry(
    array $axes = [],
    array $defaults = [],
    array $order = [],
): ScopeRegistryInterface {
    return new class ($axes, $defaults, $order) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        /** @var list<string> */
        private array $axisOrder;

        /**
         * @param array<string, list<string>> $axes
         * @param array<string, string>       $defaults
         * @param list<string>                $order
         */
        public function __construct(
            array $axes,
            array $defaults = [],
            array $order = [],
        ) {
            $this->builtAxes = [];
            foreach ($axes as $name => $paths) {
                $default = $defaults[$name] ?? $paths[0] ?? '__test_default';
                if (!in_array($default, $paths, true)) {
                    $paths = array_merge([$default], $paths);
                }
                $hierarchy = new ScopeHierarchy($paths);
                $this->builtAxes[$name] = new ScopeAxis(name: $name, hierarchy: $hierarchy, default: $default);
            }
            $this->axisOrder = $order !== [] ? $order : array_keys($this->builtAxes);
        }

        public function hasAxis(string $name): bool
        {
            return isset($this->builtAxes[$name]);
        }

        public function getAxis(string $name): ScopeAxis
        {
            if (!isset($this->builtAxes[$name])) {
                throw UnknownAxisException::forAxis($name);
            }

            return $this->builtAxes[$name];
        }

        /** @return list<string> */
        public function listAxes(): array
        {
            return $this->axisOrder;
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            return $this->getAxis($axisName)->hierarchy;
        }
    };
}

/**
 * @param array<string, list<ScopeAxisResolverInterface>> $chains  axis-name => resolver list
 */
function makePipelineChainFactory(array $chains = []): ScopeResolverChainFactory
{
    return new class ($chains) extends ScopeResolverChainFactory
    {
        /** @param array<string, list<ScopeAxisResolverInterface>> $chains */
        public function __construct(private readonly array $chains)
        {
            // skip parent constructor — no container or config needed
        }

        public function for(string $axisName): array
        {
            return $this->chains[$axisName] ?? [];
        }
    };
}

function makePipelineRequest(): Request
{
    return new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
}

function makeFakeResolver(?string $returns): ScopeAxisResolverInterface
{
    return new class ($returns) implements ScopeAxisResolverInterface
    {
        public function __construct(private readonly ?string $returns) {}

        public function resolve(
            ScopeAxis $scopeAxis,
            ScopeResolutionContext $context,
        ): ?string {
            return $this->returns;
        }
    };
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('iterates axes in registry registration order', function (): void {
    $visited = [];

    $registry = makePipelineRegistry(
        axes: ['store' => ['default'], 'geo' => ['global'], 'locale' => ['en']],
        defaults: ['store' => 'default', 'geo' => 'global', 'locale' => 'en'],
        order: ['locale', 'geo', 'store'],
    );

    $localeResolver = new class ($visited, 'locale') implements ScopeAxisResolverInterface
    {
        /** @param list<string> $visited */
        public function __construct(
            private array &$visited,
            private readonly string $axisName,
        ) {}

        public function resolve(
            ScopeAxis $scopeAxis,
            ScopeResolutionContext $context,
        ): ?string {
            $this->visited[] = $this->axisName;

            return $scopeAxis->default;
        }
    };

    $geoResolver = new class ($visited, 'geo') implements ScopeAxisResolverInterface
    {
        /** @param list<string> $visited */
        public function __construct(
            private array &$visited,
            private readonly string $axisName,
        ) {}

        public function resolve(
            ScopeAxis $scopeAxis,
            ScopeResolutionContext $context,
        ): ?string {
            $this->visited[] = $this->axisName;

            return $scopeAxis->default;
        }
    };

    $storeResolver = new class ($visited, 'store') implements ScopeAxisResolverInterface
    {
        /** @param list<string> $visited */
        public function __construct(
            private array &$visited,
            private readonly string $axisName,
        ) {}

        public function resolve(
            ScopeAxis $scopeAxis,
            ScopeResolutionContext $context,
        ): ?string {
            $this->visited[] = $this->axisName;

            return $scopeAxis->default;
        }
    };

    $factory = makePipelineChainFactory([
        'locale' => [$localeResolver],
        'geo'    => [$geoResolver],
        'store'  => [$storeResolver],
    ]);

    $context = new ScopeContext($registry);
    $pipeline = new ScopeResolutionPipeline($registry, $context, $factory);
    $pipeline->run(makePipelineRequest(), 'http');

    expect($visited)->toBe(['locale', 'geo', 'store']);
});

it('writes the first non-null hierarchy-valid resolver result to ScopeContext for each axis', function (): void {
    $registry = makePipelineRegistry(
        axes: [
            'store' => ['default', 'eu', 'us'],
            'locale' => ['en', 'fr', 'de'],
        ],
        defaults: ['store' => 'default', 'locale' => 'en'],
    );

    $factory = makePipelineChainFactory([
        'store'  => [makeFakeResolver('eu')],
        'locale' => [makeFakeResolver('fr')],
    ]);

    $context = new ScopeContext($registry);
    $pipeline = new ScopeResolutionPipeline($registry, $context, $factory);
    $pipeline->run(makePipelineRequest(), 'http');

    expect($context->get('store'))->toBe('eu')
        ->and($context->get('locale'))->toBe('fr');
});

it('falls back to axis default when chain is empty', function (): void {
    $registry = makePipelineRegistry(
        axes: ['store' => ['default', 'eu']],
        defaults: ['store' => 'default'],
    );

    $factory = makePipelineChainFactory([]); // no resolvers for any axis

    $context = new ScopeContext($registry);
    $pipeline = new ScopeResolutionPipeline($registry, $context, $factory);
    $pipeline->run(makePipelineRequest(), 'http');

    expect($context->get('store'))->toBe('default');
});

it('exposes resolved axes to later resolvers via context resolved map', function (): void {
    $capturedResolved = [];

    $registry = makePipelineRegistry(
        axes: [
            'geo'   => ['global', 'eu'],
            'store' => ['default', 'eu-store'],
        ],
        defaults: ['geo' => 'global', 'store' => 'default'],
        order: ['geo', 'store'],
    );

    // geo resolves first
    $geoResolver = makeFakeResolver('eu');

    // store resolver captures the resolved map to assert it contains 'geo'
    $storeResolver = new class ($capturedResolved) implements ScopeAxisResolverInterface
    {
        /** @param array<string, string> $capturedResolved */
        public function __construct(private array &$capturedResolved) {}

        public function resolve(
            ScopeAxis $scopeAxis,
            ScopeResolutionContext $context,
        ): ?string {
            $this->capturedResolved = $context->resolved;

            return $scopeAxis->default;
        }
    };

    $factory = makePipelineChainFactory([
        'geo'   => [$geoResolver],
        'store' => [$storeResolver],
    ]);

    $context = new ScopeContext($registry);
    $pipeline = new ScopeResolutionPipeline($registry, $context, $factory);
    $pipeline->run(makePipelineRequest(), 'http');

    expect($capturedResolved)->toHaveKey('geo')
        ->and($capturedResolved['geo'])->toBe('eu');
});

it('propagates the request and channel into the ScopeResolutionContext for every resolver', function (): void {
    $capturedRequest = null;
    $capturedChannel = null;

    $registry = makePipelineRegistry(
        axes: ['store' => ['default']],
        defaults: ['store' => 'default'],
    );

    $resolver = new class ($capturedRequest, $capturedChannel) implements ScopeAxisResolverInterface
    {
        public function __construct(
            private mixed &$capturedRequest,
            private mixed &$capturedChannel,
        ) {}

        public function resolve(
            ScopeAxis $scopeAxis,
            ScopeResolutionContext $context,
        ): ?string {
            $this->capturedRequest = $context->request;
            $this->capturedChannel = $context->channel;

            return $scopeAxis->default;
        }
    };

    $factory = makePipelineChainFactory(['store' => [$resolver]]);

    $request = new Request(server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/checkout']);
    $context = new ScopeContext($registry);
    $pipeline = new ScopeResolutionPipeline($registry, $context, $factory);
    $pipeline->run($request, 'http');

    expect($capturedRequest)->toBe($request)
        ->and($capturedChannel)->toBe('http');
});

it('falls back to axis default when all resolvers return null', function (): void {
    $registry = makePipelineRegistry(
        axes: ['store' => ['default', 'eu']],
        defaults: ['store' => 'default'],
    );

    $factory = makePipelineChainFactory([
        'store' => [
            makeFakeResolver(null),
            makeFakeResolver(null),
        ],
    ]);

    $context = new ScopeContext($registry);
    $pipeline = new ScopeResolutionPipeline($registry, $context, $factory);
    $pipeline->run(makePipelineRequest(), 'http');

    expect($context->get('store'))->toBe('default');
});

it('skips a resolver that returns a path not in the axis hierarchy and tries the next one', function (): void {
    $registry = makePipelineRegistry(
        axes: ['store' => ['default', 'eu']],
        defaults: ['store' => 'default'],
    );

    $factory = makePipelineChainFactory([
        'store' => [
            makeFakeResolver('nonexistent-path'), // invalid path, should be skipped
            makeFakeResolver('eu'),               // valid path, should be accepted
        ],
    ]);

    $context = new ScopeContext($registry);
    $pipeline = new ScopeResolutionPipeline($registry, $context, $factory);
    $pipeline->run(makePipelineRequest(), 'http');

    expect($context->get('store'))->toBe('eu');
});

it('skips a resolver that throws an exception and tries the next one', function (): void {
    $registry = makePipelineRegistry(
        axes: ['store' => ['default', 'eu']],
        defaults: ['store' => 'default'],
    );

    $throwingResolver = new class () implements ScopeAxisResolverInterface
    {
        public function resolve(
            ScopeAxis $scopeAxis,
            ScopeResolutionContext $context,
        ): ?string {
            throw new RuntimeException('Resolver exploded');
        }
    };

    $factory = makePipelineChainFactory([
        'store' => [
            $throwingResolver,
            makeFakeResolver('eu'), // should be reached after the throwing resolver is skipped
        ],
    ]);

    $context = new ScopeContext($registry);
    $pipeline = new ScopeResolutionPipeline($registry, $context, $factory);
    $pipeline->run(makePipelineRequest(), 'http');

    expect($context->get('store'))->toBe('eu');
});

it('never propagates a Throwable thrown by a resolver out of run', function (): void {
    $registry = makePipelineRegistry(
        axes: ['store' => ['default']],
        defaults: ['store' => 'default'],
    );

    $throwingResolver = new class () implements ScopeAxisResolverInterface
    {
        public function resolve(
            ScopeAxis $scopeAxis,
            ScopeResolutionContext $context,
        ): ?string {
            throw new Error('Fatal error from resolver');
        }
    };

    $factory = makePipelineChainFactory(['store' => [$throwingResolver]]);

    $context = new ScopeContext($registry);
    $pipeline = new ScopeResolutionPipeline($registry, $context, $factory);

    // Must not throw anything
    expect(fn () => $pipeline->run(makePipelineRequest(), 'http'))->not->toThrow(Throwable::class);
});

it('logs ScopeResolutionException via the injected logger when a resolver fails', function (): void {
    $loggedMessages = [];

    $logger = new class ($loggedMessages) implements LoggerInterface
    {
        /** @param list<string> $loggedMessages */
        public function __construct(private array &$loggedMessages) {}

        public function emergency(
            string $message,
            array $context = [],
        ): void {}

        public function alert(
            string $message,
            array $context = [],
        ): void {}

        public function critical(
            string $message,
            array $context = [],
        ): void {}

        public function error(
            string $message,
            array $context = [],
        ): void {
            $this->loggedMessages[] = $message;
        }

        public function warning(
            string $message,
            array $context = [],
        ): void {}

        public function notice(
            string $message,
            array $context = [],
        ): void {}

        public function info(
            string $message,
            array $context = [],
        ): void {}

        public function debug(
            string $message,
            array $context = [],
        ): void {}

        public function log(
            LogLevel $level,
            string $message,
            array $context = [],
        ): void {}
    };

    $registry = makePipelineRegistry(
        axes: ['store' => ['default']],
        defaults: ['store' => 'default'],
    );

    $throwingResolver = new class () implements ScopeAxisResolverInterface
    {
        public function resolve(
            ScopeAxis $scopeAxis,
            ScopeResolutionContext $context,
        ): ?string {
            throw new RuntimeException('Resolver exploded');
        }
    };

    $factory = makePipelineChainFactory(['store' => [$throwingResolver]]);

    $context = new ScopeContext($registry);
    $pipeline = new ScopeResolutionPipeline($registry, $context, $factory, $logger);
    $pipeline->run(makePipelineRequest(), 'http');

    expect($loggedMessages)->toHaveCount(1)
        ->and($loggedMessages[0])->toContain('store');
});

it('operates correctly when the logger is null', function (): void {
    $registry = makePipelineRegistry(
        axes: ['store' => ['default', 'eu']],
        defaults: ['store' => 'default'],
    );

    $throwingResolver = new class () implements ScopeAxisResolverInterface
    {
        public function resolve(
            ScopeAxis $scopeAxis,
            ScopeResolutionContext $context,
        ): ?string {
            throw new RuntimeException('Resolver exploded');
        }
    };

    $factory = makePipelineChainFactory([
        'store' => [
            $throwingResolver,
            makeFakeResolver('eu'),
        ],
    ]);

    $context = new ScopeContext($registry);
    // Pass null explicitly as logger
    $pipeline = new ScopeResolutionPipeline($registry, $context, $factory, null);

    // Must not throw — logger is null but pipeline still operates correctly
    $pipeline->run(makePipelineRequest(), 'http');

    expect($context->get('store'))->toBe('eu');
});

it('clear delegates to ScopeContext clearAll', function (): void {
    $registry = makePipelineRegistry(
        axes: ['store' => ['default', 'eu'], 'locale' => ['en', 'fr']],
        defaults: ['store' => 'default', 'locale' => 'en'],
    );

    $factory = makePipelineChainFactory([
        'store'  => [makeFakeResolver('eu')],
        'locale' => [makeFakeResolver('fr')],
    ]);

    $context = new ScopeContext($registry);
    $pipeline = new ScopeResolutionPipeline($registry, $context, $factory);
    $pipeline->run(makePipelineRequest(), 'http');

    // Sanity-check that both axes were set
    expect($context->get('store'))->toBe('eu')
        ->and($context->get('locale'))->toBe('fr');

    $pipeline->clear();

    // After clear, both axes must be wiped
    expect($context->get('store'))->toBeNull()
        ->and($context->get('locale'))->toBeNull()
        ->and($context->state())->toBe([]);
});

// Regression: clear() must wipe ALL axes including those set by user code outside the pipeline.
// This ensures the HTTP request boundary means a truly fresh ScopeContext.
it('clear wipes axes set by user code outside the pipeline', function (): void {
    $registry = makePipelineRegistry(
        axes: ['store' => ['default'], 'locale' => ['en']],
        defaults: ['store' => 'default', 'locale' => 'en'],
    );

    $factory = makePipelineChainFactory([]);

    $context = new ScopeContext($registry);
    // User code sets an axis directly (e.g. from nested middleware)
    $context->in('locale', 'en');

    $pipeline = new ScopeResolutionPipeline($registry, $context, $factory);
    $pipeline->clear();

    // The user-set axis must also be gone
    expect($context->get('locale'))->toBeNull()
        ->and($context->state())->toBe([]);
});
