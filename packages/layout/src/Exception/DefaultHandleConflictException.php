<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exception;

class DefaultHandleConflictException extends LayoutException
{
    public static function forField(string $field): self
    {
        return new self(
            message: "The 'default' handle must not declare '$field'.",
            context: "Compiling the 'default' handle — the reserved default handle cannot use '$field' because it serves as the fallback for all unmatched requests.",
            suggestion: "Remove '$field' from the 'default' handle definition. Use a named handle with 'inherits:' if you need to compose behavior.",
        );
    }
}
