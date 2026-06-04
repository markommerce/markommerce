<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\MatchedRoute;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcherInterface;
use Markommerce\Layout\Cache\ArtifactReaderInterface;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Middleware\MarkommerceLayoutMiddleware;
use Markommerce\Layout\Runtime\RendererInterface;

// =============================================================================
// Fakes
// =============================================================================

class MLM_FakeRouteMatcher implements RouteMatcherInterface
{
    private ?MatchedRoute $matched;

    public function __construct(?MatchedRoute $matched)
    {
        $this->matched = $matched;
    }

    public function match(
        string $method,
        string $path,
    ): ?MatchedRoute {
        return $this->matched;
    }
}

class MLM_FakeArtifactReader implements ArtifactReaderInterface
{
    /** @var array<string, PreparedTree>|null */
    private ?array $artifact;

    private bool $shouldThrow;

    /** @param array<string, PreparedTree>|null $artifact */
    public function __construct(
        ?array $artifact = null,
        bool $shouldThrow = false,
    ) {
        $this->artifact = $artifact;
        $this->shouldThrow = $shouldThrow;
    }

    /**
     * @return array<string, PreparedTree>
     * @throws RuntimeException
     */
    public function read(): array
    {
        if ($this->shouldThrow) {
            throw new RuntimeException(
                'Layout artifact not found at "/var/cache/layouts.php". Run "layout:compile" to generate it.',
            );
        }

        return $this->artifact ?? [];
    }
}

class MLM_FakeRenderer implements RendererInterface
{
    public bool $renderCalled = false;

    /** @var array<string, string>|null */
    public ?array $lastRouteParams = null;

    public function render(
        PreparedTree $tree,
        Request $request,
        array $routeParams,
    ): string {
        $this->renderCalled = true;
        $this->lastRouteParams = $routeParams;

        return '<html>rendered layout</html>';
    }
}

class MLM_FakeContainer implements ContainerInterface
{
    public function get(string $id): mixed
    {
        if (class_exists($id)) {
            return new $id();
        }
        throw new RuntimeException("No binding for $id");
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

    public function call(Closure $callable): mixed
    {
        return $callable();
    }
}

// =============================================================================
// Helpers
// =============================================================================

function mlm_makeRequest(): Request
{
    return new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/products/1']);
}

function mlm_makeRoute(string $controller, string $action, string $path = '/products/{id}'): MatchedRoute
{
    return new MatchedRoute(
        route: new RouteDefinition(
            method: 'GET',
            path: $path,
            controller: $controller,
            action: $action,
        ),
        parameters: [],
    );
}

function mlm_makeTree(string $handleKey): PreparedTree
{
    return new PreparedTree(
        handleKey: $handleKey,
        template: null,
        slots: [],
        context: [],
    );
}

// =============================================================================
// Requirement 1: it renders the layout for a route that has a compiled handle
// =============================================================================

it('renders the layout for a route that has a compiled handle', function (): void {
    $controller = 'App\Controller\ProductController';
    $action = 'show';
    $handleKey = $controller . '::' . $action;

    $matched = mlm_makeRoute($controller, $action);
    $tree = mlm_makeTree($handleKey);

    $routeMatcher = new MLM_FakeRouteMatcher($matched);
    $artifactReader = new MLM_FakeArtifactReader([$handleKey => $tree]);
    $renderer = new MLM_FakeRenderer();

    $middleware = new MarkommerceLayoutMiddleware(
        routeMatcher: $routeMatcher,
        artifactReader: $artifactReader,
        renderer: $renderer,
        container: new MLM_FakeContainer(),
    );

    $request = mlm_makeRequest();
    $next = static fn (Request $r): Response => new Response('controller output', 200);

    $response = $middleware->handle($request, $next);

    expect($renderer->renderCalled)->toBeTrue();
    expect($response->statusCode())->toBe(200);
    expect($response->body())->toContain('rendered layout');
});

// =============================================================================
// Requirement 2: it falls through to normal dispatch for a route with no layout
// =============================================================================

it('falls through to normal dispatch for a route with no layout', function (): void {
    $controller = 'App\Controller\ApiController';
    $action = 'index';
    $handleKey = $controller . '::' . $action;

    $matched = mlm_makeRoute($controller, $action, '/api/items');
    // Artifact has no entry for this handle
    $tree = mlm_makeTree('SomeOtherController::show');

    $routeMatcher = new MLM_FakeRouteMatcher($matched);
    $artifactReader = new MLM_FakeArtifactReader(['SomeOtherController::show' => $tree]);
    $renderer = new MLM_FakeRenderer();

    $middleware = new MarkommerceLayoutMiddleware(
        routeMatcher: $routeMatcher,
        artifactReader: $artifactReader,
        renderer: $renderer,
        container: new MLM_FakeContainer(),
    );

    $request = mlm_makeRequest();
    $nextCalled = false;
    $next = static function (Request $r) use (&$nextCalled): Response {
        $nextCalled = true;

        return new Response('normal dispatch', 200);
    };

    $response = $middleware->handle($request, $next);

    expect($renderer->renderCalled)->toBeFalse();
    expect($nextCalled)->toBeTrue();
    expect($response->body())->toBe('normal dispatch');
});

// =============================================================================
// Requirement 3: it runs the controller action before rendering the layout
// =============================================================================

it('runs the controller action before rendering the layout', function (): void {
    $controller = 'App\Controller\ProductController';
    $action = 'show';
    $handleKey = $controller . '::' . $action;

    $matched = mlm_makeRoute($controller, $action);
    $tree = mlm_makeTree($handleKey);

    $routeMatcher = new MLM_FakeRouteMatcher($matched);
    $artifactReader = new MLM_FakeArtifactReader([$handleKey => $tree]);
    $renderer = new MLM_FakeRenderer();

    $middleware = new MarkommerceLayoutMiddleware(
        routeMatcher: $routeMatcher,
        artifactReader: $artifactReader,
        renderer: $renderer,
        container: new MLM_FakeContainer(),
    );

    $request = mlm_makeRequest();
    $nextCalled = false;
    $next = static function (Request $r) use (&$nextCalled): Response {
        $nextCalled = true;

        return new Response('controller output', 200);
    };

    $middleware->handle($request, $next);

    expect($nextCalled)->toBeTrue();
});

// =============================================================================
// Requirement 4: it honors a controller redirect instead of rendering the layout
// =============================================================================

it('honors a controller redirect instead of rendering the layout', function (): void {
    $controller = 'App\Controller\ProductController';
    $action = 'show';
    $handleKey = $controller . '::' . $action;

    $matched = mlm_makeRoute($controller, $action);
    $tree = mlm_makeTree($handleKey);

    $routeMatcher = new MLM_FakeRouteMatcher($matched);
    $artifactReader = new MLM_FakeArtifactReader([$handleKey => $tree]);
    $renderer = new MLM_FakeRenderer();

    $middleware = new MarkommerceLayoutMiddleware(
        routeMatcher: $routeMatcher,
        artifactReader: $artifactReader,
        renderer: $renderer,
        container: new MLM_FakeContainer(),
    );

    $request = mlm_makeRequest();
    $next = static fn (Request $r): Response => Response::redirect('/login');

    $response = $middleware->handle($request, $next);

    expect($renderer->renderCalled)->toBeFalse();
    expect($response->statusCode())->toBe(302);
    expect($response->headers())->toHaveKey('Location');
    expect($response->headers()['Location'])->toBe('/login');
});

// =============================================================================
// Requirement 5: it passes route parameters through to the renderer
// =============================================================================

it('passes route parameters through to the renderer', function (): void {
    $controller = 'App\Controller\ProductController';
    $action = 'show';
    $handleKey = $controller . '::' . $action;

    $routeParams = ['id' => '42', 'slug' => 'my-product'];
    $route = new RouteDefinition(
        method: 'GET',
        path: '/products/{id}/{slug}',
        controller: $controller,
        action: $action,
    );
    $matched = new MatchedRoute(route: $route, parameters: $routeParams);
    $tree = mlm_makeTree($handleKey);

    $routeMatcher = new MLM_FakeRouteMatcher($matched);
    $artifactReader = new MLM_FakeArtifactReader([$handleKey => $tree]);
    $renderer = new MLM_FakeRenderer();

    $middleware = new MarkommerceLayoutMiddleware(
        routeMatcher: $routeMatcher,
        artifactReader: $artifactReader,
        renderer: $renderer,
        container: new MLM_FakeContainer(),
    );

    $request = mlm_makeRequest();
    $next = static fn (Request $r): Response => new Response('ok', 200);

    $middleware->handle($request, $next);

    expect($renderer->lastRouteParams)->toBe($routeParams);
});

// =============================================================================
// Requirement 6: it throws a clear error when the compiled artifact is missing
// =============================================================================

it('throws a clear error when the compiled artifact is missing', function (): void {
    $controller = 'App\Controller\ProductController';
    $action = 'show';

    $matched = mlm_makeRoute($controller, $action);

    $routeMatcher = new MLM_FakeRouteMatcher($matched);
    $artifactReader = new MLM_FakeArtifactReader(shouldThrow: true);
    $renderer = new MLM_FakeRenderer();

    $middleware = new MarkommerceLayoutMiddleware(
        routeMatcher: $routeMatcher,
        artifactReader: $artifactReader,
        renderer: $renderer,
        container: new MLM_FakeContainer(),
    );

    $request = mlm_makeRequest();
    $next = static fn (Request $r): Response => new Response('ok', 200);

    expect(fn () => $middleware->handle($request, $next))
        ->toThrow(RuntimeException::class, 'layout:compile');
});

// =============================================================================
// Requirement 7: it is declared as a global middleware in module.php
// =============================================================================

it('is declared as a global middleware in module.php', function (): void {
    $module = require __DIR__ . '/../../../module.php';

    expect($module['globalMiddleware'] ?? [])->toContain(MarkommerceLayoutMiddleware::class);
});

// =============================================================================
// Requirement 8: it does not double-render when marko/layout's LayoutMiddleware is also active
// =============================================================================

it('does not double-render when marko/layout\'s LayoutMiddleware is also active', function (): void {
    // Scenario: MarkommerceLayoutMiddleware runs, finds no PreparedTree for this route,
    // falls through cleanly — allowing marko/layout's LayoutMiddleware to handle it.
    $controller = 'App\Controller\LegacyController';
    $action = 'index';
    $handleKey = $controller . '::' . $action;

    $matched = mlm_makeRoute($controller, $action, '/legacy');
    // Artifact has no entry for this handle
    $artifactReader = new MLM_FakeArtifactReader([]);
    $renderer = new MLM_FakeRenderer();

    $routeMatcher = new MLM_FakeRouteMatcher($matched);

    $middleware = new MarkommerceLayoutMiddleware(
        routeMatcher: $routeMatcher,
        artifactReader: $artifactReader,
        renderer: $renderer,
        container: new MLM_FakeContainer(),
    );

    $request = mlm_makeRequest();
    $markoLayoutHandled = false;
    $next = static function (Request $r) use (&$markoLayoutHandled): Response {
        // Simulate marko/layout's LayoutMiddleware rendering its own layout
        $markoLayoutHandled = true;

        return new Response('<html>marko layout</html>', 200);
    };

    $response = $middleware->handle($request, $next);

    // MarkommerceLayoutMiddleware must NOT render since no PreparedTree exists
    expect($renderer->renderCalled)->toBeFalse();
    // marko/layout's middleware handled it instead
    expect($markoLayoutHandled)->toBeTrue();
    expect($response->body())->toBe('<html>marko layout</html>');
});
