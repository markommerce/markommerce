<?php

declare(strict_types=1);

namespace Markommerce\Config\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class ConfigKeyConflictException extends MarkoException
{
    /**
     * @param class-string $firstClass
     * @param class-string $secondClass
     */
    public static function forKey(
        string $key,
        string $firstClass,
        string $secondClass,
    ): self {
        return new self(
            message: "Config key '$key' is claimed by both '$firstClass' and '$secondClass'",
            context: "Registering config definitions — duplicate key '$key' detected",
            suggestion: "Remove the duplicate #[Config('$key')] declaration from one of the two classes, or use a Preference to replace one with the other",
        );
    }
}
