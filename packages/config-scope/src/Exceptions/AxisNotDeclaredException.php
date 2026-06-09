<?php

declare(strict_types=1);

namespace Markommerce\ConfigScope\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class AxisNotDeclaredException extends MarkoException
{
    public static function forPropertyAndAxis(
        string $property,
        string $axis,
    ): self
    {
        return new self(
            message: "Axis '$axis' is not declared for property '$property'",
            context: "Resolving scoped config value for property '$property' with axis '$axis'",
            suggestion: "Ensure the axis '$axis' is declared via #[Scoped(axes: ['$axis'])] on property '$property', or remove the override for this axis",
        );
    }
}
