<?php

declare(strict_types=1);

namespace Markommerce\Scope\Resolver\Resolution;

use Markommerce\Scope\Axis\ScopeAxis;

interface ScopeAxisResolverInterface
{
    /**
     * Resolve the scope path for the given axis.
     * Returns null to defer to the next resolver in the chain.
     */
    public function resolve(ScopeAxis $scopeAxis, ScopeResolutionContext $scopeResolutionContext): ?string;
}
