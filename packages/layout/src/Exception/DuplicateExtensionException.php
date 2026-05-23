<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exception;

class DuplicateExtensionException extends LayoutException
{
    public static function forClass(string $class): self
    {
        return new self(
            message: "An extension of class '{$class}' is already present in the bag.",
            context: "class={$class}",
            suggestion: "Each extension class may only appear once. Create a new ExtensionBag or use a different extension class.",
        );
    }
}
