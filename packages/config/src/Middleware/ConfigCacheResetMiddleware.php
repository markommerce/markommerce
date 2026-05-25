<?php

declare(strict_types=1);

namespace Markommerce\Config\Middleware;

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Markommerce\Config\Contracts\ConfigCacheInterface;

class ConfigCacheResetMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ConfigCacheInterface $configCache,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $this->configCache->clear();

        return $next($request);
    }
}
