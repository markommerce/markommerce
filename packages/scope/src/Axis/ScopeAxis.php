<?php

declare(strict_types=1);

namespace Markommerce\Scope\Axis;

use Markommerce\Scope\Hierarchy\ScopeHierarchy;

readonly class ScopeAxis
{
    public function __construct(
        public string $name,
        public ScopeHierarchy $hierarchy,
    ) {}
}
