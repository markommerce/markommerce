<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class InvalidAttributeOptionException extends MarkoException
{
    public static function forValue(
        string $code,
        string $optionValue,
    ): self {
        return new self(
            message: "Option value '$optionValue' is not valid for attribute '$code'",
            context: "Setting attribute '$code' — the option value '$optionValue' is not among the allowed options for this attribute",
            suggestion: "Use one of the defined option values for attribute '$code', or add '$optionValue' to the attribute's option list",
        );
    }
}
