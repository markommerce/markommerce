<?php

declare(strict_types=1);

namespace Markommerce\Layout\Middleware;

use Marko\Core\Container\ContainerInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Routing\RouteMatcherInterface;
use Markommerce\Layout\Cache\ArtifactReaderInterface;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Contracts\ContextProvider;
use Markommerce\Layout\Contracts\HandleProvider;
use Markommerce\Layout\Exceptions\UnknownDynamicHandleException;
use Markommerce\Layout\Runtime\RendererInterface;
use Markommerce\Layout\Runtime\ResolutionContext;
use Markommerce\Layout\Runtime\SourceResolver;
use Markommerce\Layout\Runtime\TreeMerger;
use RuntimeException;

/**
 * Connects routing to the Markommerce layout renderer.
 *
 * Flow:
 *   1. Match the request to a route. No match → fall through.
 *   2. Compute the handleKey (ControllerFQCN::action).
 *   3. Read the compiled artifact. If the artifact is missing, throw a clear error.
 *   4. Look up the PreparedTree for the handle. No tree → fall through (clean coexistence
 *      with marko/layout's LayoutMiddleware).
 *   5. If the base tree declares handleProviders, resolve them to build the dynamic handle list,
 *      fetch each dynamic tree from the artifact, and merge all trees into one.
 *   6. Run $next($request) for controller side-effects (auth, redirects).
 *      If the response is non-2xx, honor it and return early.
 *   7. Render the (possibly merged) PreparedTree and return an HTML Response.
 *
 * Redirect/short-circuit detection: any response with a status code outside
 * the 200–299 range is treated as a short-circuit and returned as-is.
 *
 * @throws RuntimeException|UnknownDynamicHandleException
 */
class MarkommerceLayoutMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RouteMatcherInterface $routeMatcher,
        private ArtifactReaderInterface $artifactReader,
        private RendererInterface $renderer,
        private ContainerInterface $container,
    ) {}

    /**
     * @throws RuntimeException|UnknownDynamicHandleException
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

        // Resolve dynamic handles and merge trees if the base declares handleProviders
        $tree = $this->resolveAndMerge($tree, $artifact, $request, $matched->parameters);

        // Run controller action for side effects (authorization, session, etc.)
        $controllerResponse = $next($request);

        // Honor redirects and any non-2xx response (short-circuit)
        if ($controllerResponse->statusCode() < 200 || $controllerResponse->statusCode() >= 300) {
            return $controllerResponse;
        }

        $html = $this->renderer->render($tree, $request, $matched->parameters);

        // Preserve any headers the controller set (e.g. Link: canonical), merging
        // them with the default HTML content-type header.
        $controllerHeaders = array_filter(
            $controllerResponse->headers(),
            static fn (string $name): bool => strtolower($name) !== 'content-type',
            ARRAY_FILTER_USE_KEY,
        );

        if ($controllerHeaders === []) {
            return Response::html($html);
        }

        return new Response(
            body: $html,
            statusCode: 200,
            headers: array_merge(['Content-Type' => 'text/html; charset=utf-8'], $controllerHeaders),
        );
    }

    /**
     * Invoke declared HandleProviders, collect dynamic handle keys, fetch their trees,
     * and merge them all into the base tree.
     *
     * @param array<string, PreparedTree> $artifact
     * @param array<string, string> $routeParams
     * @throws UnknownDynamicHandleException
     */
    private function resolveAndMerge(
        PreparedTree $base,
        array $artifact,
        Request $request,
        array $routeParams,
    ): PreparedTree {
        if ($base->handleProviders === []) {
            return $base;
        }

        // Build a partial context map by running the base tree's context providers
        $contextMap = $this->buildContextMap($base, $request, $routeParams);

        $resolver = new SourceResolver();
        $baseContext = new ResolutionContext(
            request: $request,
            routeParams: $routeParams,
            contextMap: $contextMap,
            iterationItem: null,
            parentData: null,
            container: $this->container,
            placementChain: $base->handleKey,
        );

        // Invoke each ProvideHandle and accumulate handle keys (preserving first-seen order, deduplicating)
        // Track which provider returned which key for error reporting.
        /** @var array<string, string> $keyToProvider map of handle key → provider class */
        $keyToProvider = [];
        $collectedHandleKeys = [];
        foreach ($base->handleProviders as $provideHandle) {
            // Resolve props
            $resolvedProps = [];
            foreach ($provideHandle->props as $key => $source) {
                $resolvedProps[$key] = $resolver->resolve($source, $baseContext);
            }

            /** @var HandleProvider $provider */
            $provider = $this->container->get($provideHandle->provider);
            $returnedKeys = $provider->provide($resolvedProps);

            foreach ($returnedKeys as $key) {
                if (!in_array($key, $collectedHandleKeys, true)) {
                    $collectedHandleKeys[] = $key;
                    $keyToProvider[$key] = $provideHandle->provider;
                }
            }
        }

        if ($collectedHandleKeys === []) {
            return $base;
        }

        // Fetch each dynamic tree from the artifact
        $dynamicTrees = [];
        foreach ($collectedHandleKeys as $dynamicKey) {
            if (!isset($artifact[$dynamicKey])) {
                throw UnknownDynamicHandleException::forHandle(
                    $dynamicKey,
                    $keyToProvider[$dynamicKey],
                );
            }
            $dynamicTrees[] = $artifact[$dynamicKey];
        }

        $merger = new TreeMerger();

        return $merger->merge($base, $dynamicTrees);
    }

    /**
     * Build the context map by running all context providers from the base tree.
     * Mirrors Renderer::buildContextMap() — operates before rendering so that
     * HandleProviders can use context-sourced props.
     *
     * @param array<string, string> $routeParams
     * @return array<string, object>
     */
    private function buildContextMap(
        PreparedTree $tree,
        Request $request,
        array $routeParams,
    ): array {
        $contextMap = [];
        $resolver = new SourceResolver();

        foreach ($tree->context as $provide) {
            $tempContext = new ResolutionContext(
                request: $request,
                routeParams: $routeParams,
                contextMap: $contextMap,
                iterationItem: null,
                parentData: null,
                container: $this->container,
                placementChain: $tree->handleKey,
            );

            $resolvedProps = [];
            foreach ($provide->props as $key => $source) {
                $resolvedProps[$key] = $resolver->resolve($source, $tempContext);
            }

            /** @var ContextProvider $provider */
            $provider = $this->container->get($provide->provider);
            $contextMap[$provide->token] = $provider->provide($resolvedProps);
        }

        return $contextMap;
    }
}
