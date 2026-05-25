<?php

declare(strict_types=1);

namespace Markommerce\Config\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class AxisNotDeclaredException extends MarkoException
{
    public static function forPropertyAndAxis(
        string $key,
        string $axis,
    ): self {
        return new self(
            message: "Config key '$key' does not declare axis '$axis' on its property",
            context: "Writing config key '$key' with a scope signature that includes axis '$axis', but the property does not declare support for that axis",
            suggestion: "Add axis '$axis' to the #[Config] or #[Scoped] attribute on the property for key '$key', or remove '$axis' from the write signature",
        );
    }

    /**
     * @param class-string $configClass
     */
    public static function forAxisOnProperty(
        string $configClass,
        string $property,
        string $axis,
    ): self {
        return new self(
            message: "Axis '$axis' declared on property '$property' in config class '$configClass' is not registered in the scope registry",
            context: "Building config registry — validating #[Scoped] axes on property '$property' in '$configClass'",
            suggestion: "Register axis '$axis' in your scope configuration, or remove '$axis' from the #[Scoped] attribute on '$property'",
        );
    }
}
