<?php

declare(strict_types=1);

namespace Markommerce\Testing\Http;

use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Module\GlobalMiddlewareResolver;
use Marko\Routing\Exceptions\RouteConflictException;
use Marko\Routing\Exceptions\RouteException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Router;
use Marko\Routing\RoutingBootstrapper;
use Markommerce\Testing\Profile\BootedStore;
use ReflectionException;

/**
 * Full-stack HTTP dispatcher for integration tests.
 *
 * Uses RoutingBootstrapper to discover routes from all booted modules,
 * GlobalMiddlewareResolver to source global middleware from manifests,
 * and the REAL Latte view from the booted container (no fake-view override).
 *
 * Vite is handled via Approach A: the storefront profile sets vite.useDevServer=true
 * so Vite::headTags() emits dev-server <script type="module"> tags with no manifest
 * lookup — parallel-safe, no filesystem writes required.
 *
 * Layout compilation: CompileIfStaleMiddleware is included in the global middleware
 * list (sourced from module declarations). It compiles layouts when APP_ENV is 'dev'
 * or 'local', writing artifacts under the per-worker ProjectPaths base injected at
 * boot time via StoreProfile::boot($conn, $projectBasePath).
 *
 * APP_ENV: must be a non-production value ('dev' or 'local') for CompileIfStaleMiddleware
 * to trigger layout compilation. This dispatcher sets APP_ENV=dev if not already set to
 * a dev/local value so the first request always compiles the artifact.
 */
class RequestDispatcher
{
    private ?Router $router = null;

    public function __construct(
        private readonly BootedStore $store,
    ) {}

    /**
     * Build (or return the cached) Router from the booted manifests.
     *
     * Routes are discovered from all module src/ directories via RoutingBootstrapper.
     * Global middleware is resolved from module declarations via GlobalMiddlewareResolver.
     * The Router, RouteCollection, and RouteMatcherInterface are registered as container
     * instances so the layout middleware and other middleware can resolve them.
     *
     * @throws ReflectionException|RouteException|RouteConflictException
     */
    public function buildRouter(): Router
    {
        if ($this->router !== null) {
            return $this->router;
        }

        $container = $this->store->container();
        $manifests = $this->store->manifests();

        /** @var PreferenceRegistry $preferenceRegistry */
        $preferenceRegistry = $container->get(PreferenceRegistry::class);

        $globalMiddleware = (new GlobalMiddlewareResolver())->resolve($manifests);

        $bootstrapper = new RoutingBootstrapper(
            modules: $manifests,
            container: $container,
            preferenceRegistry: $preferenceRegistry,
            classFileParser: new ClassFileParser(),
        );

        // Ensure APP_ENV is a dev/local value so CompileIfStaleMiddleware compiles
        // layout artifacts on demand into the per-worker ProjectPaths base.
        $this->ensureDevEnvironment();

        $this->router = $bootstrapper->boot($globalMiddleware);

        return $this->router;
    }

    /**
     * Dispatch an HTTP request through the full middleware + routing pipeline.
     *
     * The pipeline order is:
     *   1. CompileIfStaleMiddleware — compiles layout artifacts when stale (dev/local only)
     *   2. MarkommerceLayoutMiddleware — matches layout tree and renders real Latte HTML
     *   3. Controller — executed inside the middleware pipeline for side effects
     *
     * Short-circuit responses (3xx, 4xx, 5xx) from the controller are passed through
     * by MarkommerceLayoutMiddleware unchanged.
     *
     * @throws ReflectionException|RouteException|RouteConflictException
     */
    public function dispatch(Request $request): Response
    {
        return $this->buildRouter()->handle($request);
    }

    /**
     * Ensure APP_ENV is set to a dev/local value so CompileIfStaleMiddleware compiles.
     *
     * CompileIfStaleMiddleware reads getenv('APP_ENV') in its module.php binding and only
     * compiles when the value is 'dev' or 'local'. If APP_ENV is not set or is 'production',
     * this method sets it to 'dev' for the duration of the test process.
     *
     * Note: this only affects the current process; parallel workers each set their own env.
     */
    private function ensureDevEnvironment(): void
    {
        $env = (string) (getenv('APP_ENV') ?: '');

        if (!in_array($env, ['dev', 'local'], true)) {
            putenv('APP_ENV=dev');
        }
    }
}
