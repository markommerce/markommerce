<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Middleware;

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Markommerce\LayoutDemo\Config\LayoutDemoConfig;

readonly class EnsureLayoutDemoEnabledMiddleware implements MiddlewareInterface
{
    public function __construct(
        private LayoutDemoConfig $layoutDemoConfig,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        if (!$this->layoutDemoConfig->isEnabled()) {
            return new Response('Not Found', 404);
        }

        return $next($request);
    }
}
