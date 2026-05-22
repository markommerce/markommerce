<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exception;

class InvalidSourceTypeException extends LayoutException
{
    public static function forSource(string $source, string $value, string $targetType): self
    {
        return new self(
            message: "Invalid source type from '$source': got '$value', expected '$targetType'.",
            context: "Resolving source '$source' — the resolved value of type '$value' cannot be used as '$targetType'.",
            suggestion: "Ensure the source '$source' resolves to a value compatible with '$targetType'.",
        );
    }
}
