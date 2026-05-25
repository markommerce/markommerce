<?php

declare(strict_types=1);

namespace Markommerce\Scope\Resolver\Resolution\Builtin;

use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Resolver\Resolution\ScopeAxisResolverInterface;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;

readonly class QueryParamResolver implements ScopeAxisResolverInterface
{
    public function __construct(private string $paramName) {}

    public function resolve(
        ScopeAxis $scopeAxis,
        ScopeResolutionContext $scopeResolutionContext,
    ): ?string
    {
        if ($scopeResolutionContext->channel !== ScopeResolutionContext::CHANNEL_HTTP) {
            return null;
        }

        $value = $scopeResolutionContext->request->query($this->paramName);

        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
