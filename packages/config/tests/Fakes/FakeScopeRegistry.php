<?php

declare(strict_types=1);

namespace Markommerce\Config\Tests\Fakes;

use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

class FakeScopeRegistry implements ScopeRegistryInterface
{
    /** @var list<string> */
    private array $axes;

    /**
     * @param list<string> $axes
     */
    public function __construct(array $axes = [])
    {
        $this->axes = $axes;
    }

    public function hasAxis(string $name): bool
    {
        return in_array($name, $this->axes, true);
    }

    /**
     * @throws UnknownAxisException
     */
    public function getAxis(string $name): ScopeAxis
    {
        if (!$this->hasAxis($name)) {
            throw UnknownAxisException::forAxis($name);
        }

        return new ScopeAxis(
            name: $name,
            hierarchy: ScopeHierarchy::fromPaths(['default']),
            default: 'default',
        );
    }

    /**
     * @return list<string>
     */
    public function listAxes(): array
    {
        return $this->axes;
    }

    /**
     * @throws UnknownAxisException
     */
    public function getHierarchy(string $axisName): ScopeHierarchy
    {
        return $this->getAxis($axisName)->hierarchy;
    }
}
