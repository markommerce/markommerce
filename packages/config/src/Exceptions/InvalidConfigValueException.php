<?php

declare(strict_types=1);

namespace Markommerce\Config\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class InvalidConfigValueException extends MarkoException
{
    public static function forKey(
        string $key,
        string $rawValue,
        string $targetType,
    ): self
    {
        return new self(
            message: "Cannot cast stored value '$rawValue' for config key '$key' to type '$targetType'",
            context: "Reading config key '$key' — the stored value '$rawValue' cannot be coerced into '$targetType'",
            suggestion: "Update the stored value for '$key' to a value compatible with '$targetType', or check whether the config class property type matches the intended storage format",
        );
    }
}
