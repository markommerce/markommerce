<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exception;

class UnknownDynamicHandleException extends LayoutException
{
    public static function forHandle(string $handle, string $providerClass): self
    {
        return new self(
            message: "Handle provider '$providerClass' returned handle key '$handle', which does not exist in the compiled artifact.",
            context: "Resolving dynamic handle at runtime — '$providerClass' returned '$handle', but no handle with that key was found.",
            suggestion: "Ensure '$providerClass' only returns handle keys that are defined and compiled. Add handle '$handle' to a layout file or correct the provider logic.",
        );
    }
}
