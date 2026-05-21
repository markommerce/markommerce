<?php

declare(strict_types=1);

namespace Markommerce\Scope\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Throwable;

/**
 * Exception thrown when a resolver fails unexpectedly at runtime.
 */
class ScopeResolutionException extends MarkoException
{
    public static function resolverFailed(string $resolverClass, string $axisName, Throwable $previous): self
    {
        return new self(
            message: "Resolver '$resolverClass' for axis '$axisName' threw an unexpected exception",
            context: "Running resolver '$resolverClass' for axis '$axisName'",
            suggestion: "Check the resolver implementation for bugs or missing dependencies.",
            previous: $previous,
        );
    }

    public static function invalidPath(string $resolverClass, string $axisName, string $path): self
    {
        return new self(
            message: "Resolver '$resolverClass' for axis '$axisName' returned an invalid path '$path' not found in the axis hierarchy",
            context: "Running resolver '$resolverClass' for axis '$axisName'",
            suggestion: "Ensure the resolver returns a valid scope path that exists in the '$axisName' axis hierarchy, or return null to defer to the next resolver.",
        );
    }
}
