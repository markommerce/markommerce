<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exceptions;

class MissingPropException extends LayoutException
{
    public static function forProp(
        string $prop,
        string $component,
    ): self
    {
        return new self(
            message: "Required prop '$prop' is missing for component '$component'.",
            context: "Rendering component '$component' — required prop '$prop' was not provided.",
            suggestion: "Pass the '$prop' prop when rendering '$component' or mark it optional if it is not always required.",
        );
    }

    public static function forPropWithChain(
        string $prop,
        string $component,
        string $chain,
    ): self
    {
        return new self(
            message: "Required prop '$prop' is missing for component '$component'.",
            context: "Validating component '$component' at [$chain] — required prop '$prop' was not provided.",
            suggestion: "Pass the '$prop' prop in the layout definition for '$component'.",
        );
    }
}
