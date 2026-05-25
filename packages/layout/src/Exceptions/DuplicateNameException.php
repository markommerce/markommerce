<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exceptions;

class DuplicateNameException extends LayoutException
{
    public static function forName(string $name): self
    {
        return new self(
            message: "Duplicate placement name '$name'.",
            context: "Registering placement '$name' — a placement with this name already exists in the layout.",
            suggestion: 'Rename one of the conflicting placements or remove the duplicate declaration.',
        );
    }

    public static function forNameWithChain(
        string $name,
        string $chain,
    ): self
    {
        return new self(
            message: "Duplicate placement name '$name'.",
            context: "Registering placement '$name' at [$chain] — a placement with this name already exists in the layout.",
            suggestion: 'Rename one of the conflicting placements or remove the duplicate declaration.',
        );
    }
}
