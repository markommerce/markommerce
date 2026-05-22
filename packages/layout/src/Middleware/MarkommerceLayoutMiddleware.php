<?php

declare(strict_types=1);

namespace Markommerce\Layout\Middleware;

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Routing\RouteMatcherInterface;
use Markommerce\Layout\Cache\ArtifactReaderInterface;
use Markommerce\Layout\Runtime\RendererInterface;

/**
 * Connects routing to the Markommerce layout renderer.
 *
 * Flow:
 *   1. Match the request to a route. No match → fall through.
 *   2. Compute the handleKey (ControllerFQCN::action).
 *   3. Read the compiled artifact. If the artifact is missing, throw a clear error.
 *   4. Look up the PreparedTree for the handle. No tree → fall through (clean coexistence
 *      with marko/layout's LayoutMiddleware).
 *   5. Run $next($request) for controller side-effects (auth, redirects).
 *      If the response is non-2xx, honor it and return early.
 *   6. Render the PreparedTree and return an HTML Response.
 *
 * Redirect/short-circuit detection: any response with a status code outside
 * the 200–299 range is treated as a short-circuit and returned as-is.
 *
 * @throws \RuntimeException When the compiled artifact is missing.
 */
class MarkommerceLayoutMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RouteMatcherInterface $routeMatcher,
        private ArtifactReaderInterface $artifactReader,
        private RendererInterface $renderer,
    ) {}

    /**
     * @throws \RuntimeException
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $matched = $this->routeMatcher->match($request->method(), $request->path());

        if ($matched === null) {
            return $next($request);
        }

        $handleKey = $matched->route->controller . '::' . $matched->route->action;

        // This throws RuntimeException with a suggestion to run layout:compile if the file is missing.
        $artifact = $this->artifactReader->read();

        if (!isset($artifact[$handleKey])) {
            return $next($request);
        }

        $tree = $artifact[$handleKey];

        // Run controller action for side effects (authorization, session, etc.)
        $controllerResponse = $next($request);

        // Honor redirects and any non-2xx response (short-circuit)
        if ($controllerResponse->statusCode() < 200 || $controllerResponse->statusCode() >= 300) {
            return $controllerResponse;
        }

        $html = $this->renderer->render($tree, $request, $matched->parameters);

        return Response::html($html);
    }
}
