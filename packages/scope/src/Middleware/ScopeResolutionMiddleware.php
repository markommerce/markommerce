<?php

declare(strict_types=1);

namespace Markommerce\Scope\Middleware;

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionPipeline;

readonly class ScopeResolutionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ScopeResolutionPipeline $scopeResolutionPipeline,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $this->scopeResolutionPipeline->run($request, ScopeResolutionContext::CHANNEL_HTTP);

        try {
            $response = $next($request);
        } finally {
            $this->scopeResolutionPipeline->clear();
        }

        return $response;
    }
}
