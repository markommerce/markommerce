<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exception;

class ExtensionConflictException extends LayoutException
{
    public static function forConflict(string $operation, string $conflictingOperation, int $priority): self
    {
        return new self(
            message: "Extension conflict between '$operation' and '$conflictingOperation' at priority $priority.",
            context: "Applying layout extension operations '$operation' and '$conflictingOperation' both registered at priority $priority.",
            suggestion: "Assign distinct priority values to '$operation' and '$conflictingOperation' to resolve the conflict.",
        );
    }
}
