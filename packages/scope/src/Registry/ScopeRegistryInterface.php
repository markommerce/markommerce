<?php

declare(strict_types=1);

namespace Markommerce\Scope\Registry;

use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;

interface ScopeRegistryInterface
{
    public function hasAxis(string $name): bool;

    /**
     * @throws UnknownAxisException
     */
    public function getAxis(string $name): ScopeAxis;

    /**
     * @return list<string>
     */
    public function listAxes(): array;

    /**
     * @throws UnknownAxisException
     */
    public function getHierarchy(string $axisName): ScopeHierarchy;
}
