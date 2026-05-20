<?php

declare(strict_types=1);

namespace Markommerce\Scope\Resolver\Resolution\Builtin;

use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Resolver\Resolution\ScopeAxisResolverInterface;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;

readonly class StaticResolver implements ScopeAxisResolverInterface
{
    public function __construct(private string $value) {}

    public function resolve(ScopeAxis $scopeAxis, ScopeResolutionContext $scopeResolutionContext): ?string
    {
        return $this->value;
    }
}
