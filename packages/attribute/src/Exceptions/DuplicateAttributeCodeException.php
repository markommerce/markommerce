<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class DuplicateAttributeCodeException extends MarkoException
{
    public static function forCode(
        string $entityType,
        string $code,
    ): self
    {
        return new self(
            message: "Attribute code '$code' already exists for entity type '$entityType'",
            context: "Registering attribute '$code' for entity type '$entityType' — an attribute with this code is already registered",
            suggestion: "Use a unique attribute code for entity type '$entityType', or remove the existing attribute before registering a new one with the same code",
        );
    }
}
