<?php

declare(strict_types=1);

namespace Markommerce\Testing\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Thrown when a database identifier fails the safe-identifier validation check.
 */
class InvalidIdentifierException extends MarkoException
{
    public static function forName(string $name): self
    {
        return new self(
            message: "Unsafe database identifier: '$name'",
            context: 'Database identifiers must match /^[a-zA-Z_][a-zA-Z0-9_]*$/',
            suggestion: 'Use only letters, digits, and underscores; start with a letter or underscore',
        );
    }
}
