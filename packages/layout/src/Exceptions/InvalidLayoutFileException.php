<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exceptions;

class InvalidLayoutFileException extends LayoutException
{
    public static function forWrongType(
        string $filePath,
        string $actualType,
    ): self
    {
        return new self(
            message: "Layout file '$filePath' returned '$actualType' instead of the expected type.",
            context: "Discovering layout files — '$filePath' must return a Layout or LayoutExtension instance.",
            suggestion: "Ensure '$filePath' ends with 'return new Layout(...)' or 'return new LayoutExtension(...)' as appropriate.",
        );
    }

    public static function forInvalidHandleProvider(
        string $handleKey,
        string $providerClass,
    ): self
    {
        return new self(
            message: "Handle provider '$providerClass' for handle '$handleKey' does not implement HandleProvider.",
            context: "Compiling layout '$handleKey' — each entry in handleProviders must be a class that implements Markommerce\\Layout\\Contracts\\HandleProvider.",
            suggestion: "Ensure '$providerClass' implements Markommerce\\Layout\\Contracts\\HandleProvider.",
        );
    }
}
