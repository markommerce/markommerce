<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class ReservedAttributeCodeException extends MarkoException
{
    public static function forCode(
        string $entityType,
        string $code,
    ): self
    {
        return new self(
            message: "Attribute code '$code' is reserved for entity type '$entityType'",
            context: "Registering attribute '$code' for entity type '$entityType' — this code is reserved by the system and cannot be used for custom attributes",
            suggestion: "Choose a different attribute code for entity type '$entityType' — reserved codes are used internally by the system",
        );
    }
}
