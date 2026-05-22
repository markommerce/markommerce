<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exception;

class MissingDataKeyException extends LayoutException
{
    public static function forKey(string $key, string $component): self
    {
        return new self(
            message: "Missing data key '$key' required by component '$component'.",
            context: "Preparing data for component '$component' — required key '$key' is absent from the data bag.",
            suggestion: "Ensure the data provider supplies the '$key' key before rendering '$component'.",
        );
    }

    public static function forKeyWithChain(string $key, string $component, string $chain): self
    {
        return new self(
            message: "Missing data key '$key' required by component '$component'.",
            context: "Preparing data for component '$component' at [$chain] — required key '$key' is absent from the DTO.",
            suggestion: "Add a public property '\$$key' to the DTO returned by '$component'::data().",
        );
    }
}
