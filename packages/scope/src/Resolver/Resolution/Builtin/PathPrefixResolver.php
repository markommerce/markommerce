<?php

declare(strict_types=1);

namespace Markommerce\Scope\Resolver\Resolution\Builtin;

use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Resolver\Resolution\ScopeAxisResolverInterface;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;

readonly class PathPrefixResolver implements ScopeAxisResolverInterface
{
    public function __construct(private int $segment = 0) {}

    public function resolve(
        ScopeAxis $scopeAxis,
        ScopeResolutionContext $scopeResolutionContext,
    ): ?string
    {
        if ($scopeResolutionContext->channel !== ScopeResolutionContext::CHANNEL_HTTP) {
            return null;
        }

        $path = $scopeResolutionContext->request->path();
        $parts = array_values(array_filter(explode('/', $path), fn (string $part): bool => $part !== ''));

        return $parts[$this->segment] ?? null;
    }
}
