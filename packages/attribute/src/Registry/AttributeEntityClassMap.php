<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Registry;

use Markommerce\Attribute\Exceptions\DuplicateEntityClassRegistrationException;

class AttributeEntityClassMap
{
    /**
     * @var array<string, class-string>
     */
    private array $map = [];

    /**
     * Register an entity class for the given entity type.
     *
     * Registering the same (entityType, class) pair again is a no-op.
     * Registering a DIFFERENT class for an already-registered entity type throws.
     *
     * @param class-string $entityClass
     *
     * @throws DuplicateEntityClassRegistrationException
     */
    public function register(
        string $entityType,
        string $entityClass,
    ): void
    {
        if (isset($this->map[$entityType])) {
            if ($this->map[$entityType] === $entityClass) {
                // Same (entityType, class) — idempotent no-op
                return;
            }

            throw DuplicateEntityClassRegistrationException::forEntityType(
                $entityType,
                $this->map[$entityType],
                $entityClass,
            );
        }

        $this->map[$entityType] = $entityClass;
    }

    /**
     * @return array<string, class-string>
     */
    public function all(): array
    {
        return $this->map;
    }
}
