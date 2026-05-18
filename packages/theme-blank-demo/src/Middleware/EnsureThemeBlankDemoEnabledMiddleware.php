<?php

declare(strict_types=1);

namespace Markommerce\ThemeBlankDemo\Middleware;

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Markommerce\ThemeBlankDemo\Config\ThemeBlankDemoConfig;

readonly class EnsureThemeBlankDemoEnabledMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ThemeBlankDemoConfig $themeBlankDemoConfig,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        if (!$this->themeBlankDemoConfig->isEnabled()) {
            return new Response('Not Found', 404);
        }

        return $next($request);
    }
}
