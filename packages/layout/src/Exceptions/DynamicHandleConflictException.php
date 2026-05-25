<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exceptions;

class DynamicHandleConflictException extends LayoutException
{
    public static function forCollidingPlacement(
        string $placementName,
        string $baseHandle,
        string $dynamicHandle,
    ): self
    {
        return new self(
            message: "Placement name '$placementName' is declared in both '$baseHandle' and dynamic handle '$dynamicHandle'.",
            context: "Merging dynamic handle '$dynamicHandle' into '$baseHandle' — placement name '$placementName' already exists in the base tree.",
            suggestion: "Rename the placement '$placementName' in one of the handles to avoid the collision between '$baseHandle' and '$dynamicHandle'.",
        );
    }
}
