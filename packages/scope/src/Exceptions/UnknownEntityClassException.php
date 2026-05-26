<?php

declare(strict_types=1);

namespace Markommerce\Scope\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when a class name passed to ScopedFieldRegistry does not exist.
 */
class UnknownEntityClassException extends MarkoException
{
    public static function forClass(string $entityClass): self
    {
        return new self(
            message: "Entity class '$entityClass' does not exist",
            context: "Attempting to register scoped properties for class '$entityClass'",
            suggestion: 'Check for typos in the class name and ensure the class is autoloaded before registration',
        );
    }
}
