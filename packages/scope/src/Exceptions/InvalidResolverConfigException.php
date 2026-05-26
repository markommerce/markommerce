<?php

declare(strict_types=1);

namespace Markommerce\Scope\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when a scope axis resolver configuration is invalid.
 */
class InvalidResolverConfigException extends MarkoException
{
    public static function unknownClass(
        string $className,
        string $axisName,
    ): self {
        return new self(
            message: "Resolver class '$className' for axis '$axisName' does not exist",
            context: "Validating resolver configuration for axis '$axisName'",
            suggestion: "Check that the class '$className' exists and is autoloadable. Verify the class name in your scope resolver configuration for the '$axisName' axis.",
        );
    }

    public static function missingClassKey(
        int $index,
        string $axisName,
    ): self {
        return new self(
            message: "Resolver entry at index $index for axis '$axisName' is missing the required 'class' key",
            context: "Validating resolver configuration for axis '$axisName' at index $index",
            suggestion: "Each resolver entry must have a 'class' key. Add 'class' => YourResolver::class to the entry at index $index for the '$axisName' axis.",
        );
    }

    public static function notImplementingInterface(
        string $className,
        string $axisName,
    ): self {
        return new self(
            message: "Resolver class '$className' for axis '$axisName' does not implement the required interface",
            context: "Validating resolver configuration for axis '$axisName'",
            suggestion: "Ensure '$className' implements ScopeAxisResolverInterface.",
        );
    }
}
