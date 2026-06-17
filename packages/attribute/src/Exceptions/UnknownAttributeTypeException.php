<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class UnknownAttributeTypeException extends MarkoException
{
    public static function forType(string $typeCode): self
    {
        return new self(
            message: "Unknown attribute type '$typeCode'",
            context: "Resolving attribute type '$typeCode' — no handler registered for this type code",
            suggestion: "Register a type handler for '$typeCode' or use one of the built-in attribute types",
        );
    }
}
