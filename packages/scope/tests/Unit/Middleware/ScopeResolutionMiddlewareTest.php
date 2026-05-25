<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Middleware\ScopeResolutionMiddleware;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolver\Resolution\ScopeAxisResolverInterface;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionPipeline;
use Markommerce\Scope\Resolver\Resolution\ScopeResolverChainFactory;

// ─── Fakes ───────────────────────────────────────────────────────────────────

/**
 * @param array<string, list<string>> $axes
 * @param array<string, string>       $defaults
 */
function makeMiddlewareRegistry(array $axes = [], array $defaults = []): ScopeRegistryInterface
{
    return new class ($axes, $defaults) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        /**
         * @param array<string, list<string>> $axes
         * @param array<string, string>       $defaults
         */
        public function __construct(
            array $axes,
            array $defaults = [],
        )
        {
            $this->builtAxes = [];
            foreach ($axes as $name => $paths) {
                $default = $defaults[$name] ?? $paths[0] ?? '__test_default';
                if (!in_array($default, $paths, true)) {
                    $paths = array_merge([$default], $paths);
                }
                $hierarchy = new ScopeHierarchy($paths);
                $this->builtAxes[$name] = new ScopeAxis(name: $name, hierarchy: $hierarchy, default: $default);
            }
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
            return array_keys($this->builtAxes);
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            return $this->getAxis($axisName)->hierarchy;
        }
    };
}

/**
 * @param array<string, list<ScopeAxisResolverInterface>> $chains
 */
function makeMiddlewareChainFactory(array $chains = []): ScopeResolverChainFactory
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

function makeMiddlewareRequest(): Request
{
    return new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
}

function makeMiddlewareResponse(): Response
{
    return new Response(body: 'ok', statusCode: 200);
}

/**
 * Build a real ScopeResolutionPipeline with an empty registry (no axes) and an empty chain factory.
 */
function makeEmptyPipeline(): ScopeResolutionPipeline
{
    $registry = makeMiddlewareRegistry([]);
    $context = new ScopeContext($registry);
    $factory = makeMiddlewareChainFactory([]);

    return new ScopeResolutionPipeline($registry, $context, $factory);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('it runs the pipeline with http channel before calling next', function (): void {
    $capturedChannel = null;
    $nextCalled = false;
    $contextStateBeforeNext = null;

    $registry = makeMiddlewareRegistry(['store' => ['default', 'eu']], ['store' => 'default']);
    $context = new ScopeContext($registry);

    $resolver = new class ($capturedChannel) implements ScopeAxisResolverInterface
    {
        /** @phpstan-ignore property.onlyWritten */
        public function __construct(private mixed &$capturedChannel) {}

        public function resolve(
            ScopeAxis $scopeAxis,
            ScopeResolutionContext $resolutionContext,
        ): string
        {
            $this->capturedChannel = $resolutionContext->channel;

            return $scopeAxis->default;
        }
    };

    $factory = makeMiddlewareChainFactory(['store' => [$resolver]]);
    $pipeline = new ScopeResolutionPipeline($registry, $context, $factory);
    $middleware = new ScopeResolutionMiddleware($pipeline);

    $request = makeMiddlewareRequest();
    $response = makeMiddlewareResponse();

    $next = function (Request $req) use ($response, &$nextCalled, $context, &$contextStateBeforeNext): Response {
        $nextCalled = true;
        $contextStateBeforeNext = $context->state();

        return $response;
    };

    $middleware->handle($request, $next);

    expect($capturedChannel)->toBe(ScopeResolutionContext::CHANNEL_HTTP)
        ->and($nextCalled)->toBeTrue()
        ->and($contextStateBeforeNext)->toHaveKey('store');
});

it('it passes the same request through to next handler', function (): void {
    $capturedRequest = null;

    $pipeline = makeEmptyPipeline();
    $middleware = new ScopeResolutionMiddleware($pipeline);

    $request = makeMiddlewareRequest();
    $response = makeMiddlewareResponse();

    $next = function (Request $req) use ($response, &$capturedRequest): Response {
        $capturedRequest = $req;

        return $response;
    };

    $middleware->handle($request, $next);

    expect($capturedRequest)->toBe($request);
});

it('it returns the response from the next handler', function (): void {
    $pipeline = makeEmptyPipeline();
    $middleware = new ScopeResolutionMiddleware($pipeline);

    $request = makeMiddlewareRequest();
    $expectedResponse = new Response(body: 'hello world', statusCode: 201);

    $next = fn (Request $req): Response => $expectedResponse;

    $returnedResponse = $middleware->handle($request, $next);

    expect($returnedResponse)->toBe($expectedResponse);
});

it('it clears the scope context after the handler returns successfully', function (): void {
    $registry = makeMiddlewareRegistry(['store' => ['default', 'eu']], ['store' => 'default']);
    $context = new ScopeContext($registry);

    $resolver = new class () implements ScopeAxisResolverInterface
    {
        public function resolve(
            ScopeAxis $scopeAxis,
            ScopeResolutionContext $resolutionContext,
        ): string
        {
            return $scopeAxis->default;
        }
    };

    $factory = makeMiddlewareChainFactory(['store' => [$resolver]]);
    $pipeline = new ScopeResolutionPipeline($registry, $context, $factory);
    $middleware = new ScopeResolutionMiddleware($pipeline);

    $request = makeMiddlewareRequest();
    $response = makeMiddlewareResponse();

    $next = fn (Request $req): Response => $response;

    $middleware->handle($request, $next);

    // After successful response, context must be cleared
    expect($context->state())->toBe([])
        ->and($context->get('store'))->toBeNull();
});

it('it clears the scope context when the handler throws an Exception', function (): void {
    $registry = makeMiddlewareRegistry(['store' => ['default', 'eu']], ['store' => 'default']);
    $context = new ScopeContext($registry);

    $resolver = new class () implements ScopeAxisResolverInterface
    {
        public function resolve(
            ScopeAxis $scopeAxis,
            ScopeResolutionContext $resolutionContext,
        ): string
        {
            return $scopeAxis->default;
        }
    };

    $factory = makeMiddlewareChainFactory(['store' => [$resolver]]);
    $pipeline = new ScopeResolutionPipeline($registry, $context, $factory);
    $middleware = new ScopeResolutionMiddleware($pipeline);

    $request = makeMiddlewareRequest();

    $next = function (Request $req): Response {
        throw new RuntimeException('Handler failed');
    };

    try {
        $middleware->handle($request, $next);
    } catch (RuntimeException) {
        // expected
    }

    // Even after an exception, the context must be cleared
    expect($context->state())->toBe([])
        ->and($context->get('store'))->toBeNull();
});

it('it clears the scope context when the handler throws an Error (not just Exception)', function (): void {
    $registry = makeMiddlewareRegistry(['store' => ['default', 'eu']], ['store' => 'default']);
    $context = new ScopeContext($registry);

    $resolver = new class () implements ScopeAxisResolverInterface
    {
        public function resolve(
            ScopeAxis $scopeAxis,
            ScopeResolutionContext $resolutionContext,
        ): string
        {
            return $scopeAxis->default;
        }
    };

    $factory = makeMiddlewareChainFactory(['store' => [$resolver]]);
    $pipeline = new ScopeResolutionPipeline($registry, $context, $factory);
    $middleware = new ScopeResolutionMiddleware($pipeline);

    $request = makeMiddlewareRequest();

    $next = function (Request $req): Response {
        throw new Error('Fatal error in handler');
    };

    try {
        $middleware->handle($request, $next);
    } catch (Error) {
        // expected
    }

    // Even after an Error (not Exception), the context must be cleared
    expect($context->state())->toBe([])
        ->and($context->get('store'))->toBeNull();
});

it('it re-throws exceptions thrown by the handler', function (): void {
    $pipeline = makeEmptyPipeline();
    $middleware = new ScopeResolutionMiddleware($pipeline);

    $request = makeMiddlewareRequest();
    $originalException = new RuntimeException('Handler failed with specific message');

    $next = function (Request $req) use ($originalException): Response {
        throw $originalException;
    };

    expect(fn () => $middleware->handle($request, $next))
        ->toThrow(RuntimeException::class, 'Handler failed with specific message');
});
