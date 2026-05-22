<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exception;

class InvalidLayoutFileException extends LayoutException
{
    public static function forWrongType(string $filePath, string $actualType): self
    {
        return new self(
            message: "Layout file '$filePath' returned '$actualType' instead of the expected type.",
            context: "Discovering layout files — '$filePath' must return a Layout or LayoutExtension instance.",
            suggestion: "Ensure '$filePath' ends with 'return new Layout(...)' or 'return new LayoutExtension(...)' as appropriate.",
        );
    }
}
