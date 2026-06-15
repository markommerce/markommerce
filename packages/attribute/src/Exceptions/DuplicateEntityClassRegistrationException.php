<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class DuplicateEntityClassRegistrationException extends MarkoException
{
    /**
     * @param class-string $existing
     * @param class-string $attempted
     */
    public static function forEntityType(
        string $entityType,
        string $existing,
        string $attempted,
    ): self {
        return new self(
            message: "Entity type '$entityType' is already mapped to class '$existing'; cannot remap to '$attempted'",
            context: "Registering entity class '$attempted' for entity type '$entityType' — this type is already mapped to '$existing'",
            suggestion: 'Each entity type may only be mapped to one class. Remove the existing registration before adding a new one, or use the same class.',
        );
    }
}
