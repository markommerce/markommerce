<?php

declare(strict_types=1);

namespace Markommerce\Config\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class ConfigNotFoundException extends MarkoException
{
    public static function forKey(string $key): self
    {
        return new self(
            message: "Config key '$key' not found",
            context: "Resolving config key '$key'",
            suggestion: "To register this key, create a config class with a #[Config('$key')] property and bind it in your module",
        );
    }
}
