<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exception;

class DuplicateContextTokenException extends LayoutException
{
    public static function forToken(string $token, string $sourceHandle, string $targetHandle): self
    {
        return new self(
            message: "Context token '$token' from '$sourceHandle' conflicts with an existing token in '$targetHandle'.",
            context: "Merging context from '$sourceHandle' into '$targetHandle' — token '$token' is already defined on '$targetHandle' and cannot be overwritten during inheritance or default merge.",
            suggestion: "Rename context token '$token' in either '$sourceHandle' or '$targetHandle' to resolve the conflict.",
        );
    }
}
