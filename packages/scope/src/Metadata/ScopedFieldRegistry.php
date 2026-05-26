<?php

declare(strict_types=1);

namespace Markommerce\Scope\Metadata;

use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Exceptions\UnknownEntityClassException;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

/**
 * Accumulates property-to-axis mappings at boot time.
 *
 * Each entry maps a (entityClass, property) pair to a list of axes. Registrations
 * from multiple sources for the same property union their axes (no duplicates).
 *
 * This class is intentionally NOT readonly — state accumulates via register()
 * during the boot phase (module.php bridge files). Once the application has
 * booted, no further mutations should occur.
 */
class ScopedFieldRegistry
{
    /**
     * @var array<class-string, array<string, list<string>>>
     */
    private array $map = [];

    public function __construct(
        private ScopeRegistryInterface $scopeRegistry,
    ) {}

    /**
     * @param list<string> $axes
     * @throws UnknownEntityClassException|UnknownAxisException
     */
    public function register(
        string $entityClass,
        string $property,
        array $axes,
    ): void {
        if (!class_exists($entityClass) && !interface_exists($entityClass) && !enum_exists($entityClass)) {
            throw UnknownEntityClassException::forClass($entityClass);
        }

        if ($axes === []) {
            return;
        }

        foreach ($axes as $axis) {
            if (!$this->scopeRegistry->hasAxis($axis)) {
                throw UnknownAxisException::forAxis($axis);
            }
        }

        /** @var class-string $entityClass */
        if (!isset($this->map[$entityClass])) {
            $this->map[$entityClass] = [];
        }

        if (!isset($this->map[$entityClass][$property])) {
            $this->map[$entityClass][$property] = $axes;

            return;
        }

        $existing = $this->map[$entityClass][$property];
        foreach ($axes as $axis) {
            if (!in_array($axis, $existing, true)) {
                $existing[] = $axis;
            }
        }
        $this->map[$entityClass][$property] = $existing;
    }

    /**
     * @return list<string>
     */
    public function axesForProperty(
        string $entityClass,
        string $property,
    ): array {
        return $this->map[$entityClass][$property] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function propertiesFor(string $entityClass): array
    {
        return $this->map[$entityClass] ?? [];
    }

    public function hasScopedProperties(string $entityClass): bool
    {
        return isset($this->map[$entityClass]) && $this->map[$entityClass] !== [];
    }
}
