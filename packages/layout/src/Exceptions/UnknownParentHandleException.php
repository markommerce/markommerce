<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exceptions;

class UnknownParentHandleException extends LayoutException
{
    public static function forParent(
        string $parent,
        string $child,
    ): self
    {
        return new self(
            message: "Handle '$child' inherits from '$parent', but '$parent' does not exist.",
            context: "Resolving 'inherits:' declaration on handle '$child' — no handle with key '$parent' was found in the compiled artifact.",
            suggestion: "Define handle '$parent' in a layout file, or update '$child' to inherit from an existing handle.",
        );
    }
}
