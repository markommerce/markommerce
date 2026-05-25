<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exceptions;

class DanglingAnchorException extends LayoutException
{
    public static function forAnchor(
        string $anchor,
        string $extensionFile,
    ): self
    {
        return new self(
            message: "Dangling anchor '$anchor' in extension file '$extensionFile'.",
            context: "Applying layout extension at '$extensionFile' — anchor '$anchor' does not exist in the target layout.",
            suggestion: "Verify the anchor '$anchor' is defined in the target layout or remove the reference from '$extensionFile'.",
        );
    }

    public static function forAnchorWithChain(
        string $anchor,
        string $extensionFile,
        string $chain,
    ): self
    {
        return new self(
            message: "Dangling anchor '$anchor' references a placement that does not exist.",
            context: "Validating wrap marker at [$chain] — anchor '$anchor' does not exist in the resolved layout '$extensionFile'.",
            suggestion: "Verify the anchor '$anchor' is defined in the layout or remove the dangling reference.",
        );
    }
}
