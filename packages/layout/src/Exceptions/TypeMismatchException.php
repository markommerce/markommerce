<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exceptions;

class TypeMismatchException extends LayoutException
{
    public static function forProp(
        string $prop,
        string $expectedType,
        string $actualType,
    ): self
    {
        return new self(
            message: "Type mismatch for prop '$prop': expected '$expectedType', got '$actualType'.",
            context: "Validating prop '$prop' — expected type '$expectedType' but received '$actualType'.",
            suggestion: "Ensure the value passed to prop '$prop' is of type '$expectedType'.",
        );
    }

    public static function forPropWithChain(
        string $prop,
        string $expectedType,
        string $actualType,
        string $chain,
    ): self
    {
        return new self(
            message: "Type mismatch for prop '$prop': expected '$expectedType', got '$actualType'.",
            context: "Validating prop '$prop' at [$chain] — expected type '$expectedType' but received '$actualType'.",
            suggestion: "Ensure the value passed to prop '$prop' is of type '$expectedType'.",
        );
    }
}
