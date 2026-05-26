<?php

declare(strict_types=1);

namespace Markommerce\Scope\Resolver\Resolution\Builtin;

use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Resolver\Resolution\ScopeAxisResolverInterface;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;

readonly class CookieResolver implements ScopeAxisResolverInterface
{
    /**
     * @param array<string, string>|null $cookies When null, reads from $_COOKIE at resolve time.
     */
    public function __construct(
        private string $cookieName,
        private ?array $cookies = null,
    ) {}

    public function resolve(
        ScopeAxis $scopeAxis,
        ScopeResolutionContext $scopeResolutionContext,
    ): ?string {
        if ($scopeResolutionContext->channel !== ScopeResolutionContext::CHANNEL_HTTP) {
            return null;
        }

        $cookies = $this->cookies ?? $_COOKIE;

        $value = $cookies[$this->cookieName] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }
}
