<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class InvalidAttributeValueException extends MarkoException
{
    public static function forValue(
        string $code,
        string $type,
        string $raw,
    ): self
    {
        return new self(
            message: "Invalid value '$raw' for attribute '$code' of type '$type'",
            context: "Setting attribute '$code' (type: $type) — the value '$raw' is not valid for this attribute type",
            suggestion: "Provide a value that is compatible with the '$type' attribute type for attribute '$code'",
        );
    }
}
