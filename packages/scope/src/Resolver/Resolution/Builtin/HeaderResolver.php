<?php

declare(strict_types=1);

namespace Markommerce\Scope\Resolver\Resolution\Builtin;

use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Resolver\Resolution\ScopeAxisResolverInterface;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;

readonly class HeaderResolver implements ScopeAxisResolverInterface
{
    public function __construct(private string $headerName) {}

    public function resolve(ScopeAxis $scopeAxis, ScopeResolutionContext $scopeResolutionContext): ?string
    {
        if ($scopeResolutionContext->channel !== ScopeResolutionContext::CHANNEL_HTTP) {
            return null;
        }

        $value = $scopeResolutionContext->request->header($this->headerName);

        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }
}
