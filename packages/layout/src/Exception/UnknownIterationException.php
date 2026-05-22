<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exception;

class UnknownIterationException extends LayoutException
{
    public static function forIteration(string $iteration, string $placement): self
    {
        return new self(
            message: "Unknown iteration token '$iteration' at placement '$placement'.",
            context: "Resolving iteration '$iteration' at '$placement'.",
            suggestion: "Verify the iteration token is defined and bound to a valid data source for this placement.",
        );
    }

    public static function forIterationWithChain(string $iteration, string $chain): self
    {
        return new self(
            message: "Unknown iteration token '$iteration'.",
            context: "Resolving iteration '$iteration' at [$chain] — no enclosing repeat slot provides this token.",
            suggestion: "Wrap the placement inside a Slot::repeat() that uses '$iteration' as the 'as' token.",
        );
    }
}
