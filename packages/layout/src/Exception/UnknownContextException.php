<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exception;

class UnknownContextException extends LayoutException
{
    public static function forContext(string $context, string $layout): self
    {
        return new self(
            message: "Unknown context token '$context' in layout '$layout'.",
            context: "Attempting to resolve context '$context' in layout '$layout'.",
            suggestion: "Verify the context token is registered and spelled correctly in the layout configuration.",
        );
    }

    public static function forContextWithChain(string $context, string $layout, string $chain): self
    {
        return new self(
            message: "Unknown context token '$context' in layout '$layout'.",
            context: "Attempting to resolve context '$context' at [$chain].",
            suggestion: "Verify the context token is registered and spelled correctly in the layout configuration.",
        );
    }
}
