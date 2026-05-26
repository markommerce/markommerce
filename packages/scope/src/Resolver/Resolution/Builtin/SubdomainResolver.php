<?php

declare(strict_types=1);

namespace Markommerce\Scope\Resolver\Resolution\Builtin;

use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Resolver\Resolution\ScopeAxisResolverInterface;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;

readonly class SubdomainResolver implements ScopeAxisResolverInterface
{
    public function __construct(private int $segment = 0) {}

    public function resolve(
        ScopeAxis $scopeAxis,
        ScopeResolutionContext $scopeResolutionContext,
    ): ?string {
        if ($scopeResolutionContext->channel !== ScopeResolutionContext::CHANNEL_HTTP) {
            return null;
        }

        $host = $scopeResolutionContext->request->header('Host');

        if ($host === null || $host === '') {
            return null;
        }

        // Strip port suffix
        $colonPos = strpos($host, ':');
        if ($colonPos !== false) {
            $host = substr($host, 0, $colonPos);
        }

        // Reject raw IP addresses
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        $parts = explode('.', $host);

        return $parts[$this->segment] ?? null;
    }
}
