<?php

declare(strict_types=1);

namespace Markommerce\FrontendDemo\Middleware;

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Markommerce\FrontendDemo\Config\FrontendDemoConfig;

readonly class EnsureFrontendDemoEnabledMiddleware implements MiddlewareInterface
{
    public function __construct(
        private FrontendDemoConfig $frontendDemoConfig,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        if (!$this->frontendDemoConfig->isEnabled()) {
            return new Response('Not Found', 404);
        }

        return $next($request);
    }
}
