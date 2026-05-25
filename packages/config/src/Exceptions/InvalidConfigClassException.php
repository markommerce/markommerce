<?php

declare(strict_types=1);

namespace Markommerce\Config\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class InvalidConfigClassException extends MarkoException
{
    /**
     * @param class-string $configClass
     */
    public static function configClassHasRequiredConstructor(string $configClass): self
    {
        return new self(
            message: "Config class '$configClass' has required constructor parameters",
            context: "Validating config class '$configClass' — config classes must be instantiable without arguments",
            suggestion: "Remove all required constructor parameters from '$configClass', or give each parameter a default value",
        );
    }

    /**
     * @param class-string $configClass
     */
    public static function propertyMissingDefaultOrNullability(
        string $configClass,
        string $property,
    ): self
    {
        return new self(
            message: "Property '$property' in config class '$configClass' is non-nullable and has no default value",
            context: "Validating #[Config] property '$property' in '$configClass'",
            suggestion: "Either provide a default value for '$property', or declare it as nullable (e.g. ?string \$$property = null)",
        );
    }

    /**
     * @param class-string $configClass
     * @param class-string $preferenceClass
     */
    public static function nonSubclassPreference(
        string $configClass,
        string $preferenceClass,
    ): self
    {
        return new self(
            message: "Preferred class '$preferenceClass' is not a subclass of '$configClass'",
            context: "Validating config class preference: '$preferenceClass' was registered as a replacement for '$configClass'",
            suggestion: "Ensure '$preferenceClass' extends '$configClass' so it can be used as a drop-in replacement",
        );
    }

    /**
     * @param class-string $configClass
     */
    public static function propertyWithoutConfigAttribute(
        string $configClass,
        string $property,
    ): self
    {
        return new self(
            message: "Property '$property' in config class '$configClass' has no #[Config] attribute",
            context: "Validating config class '$configClass' — all public properties must carry a #[Config] attribute",
            suggestion: "Add a #[Config('key')] attribute to '$property', or remove it from the config class",
        );
    }

    /**
     * @param class-string $configClass
     */
    public static function propertyWithUnsupportedType(
        string $configClass,
        string $property,
        string $reason,
    ): self
    {
        return new self(
            message: "Property '$property' in config class '$configClass' has an unsupported type: $reason",
            context: "Validating #[Config] property '$property' in '$configClass'",
            suggestion: "Use a simple scalar or class type for '$property'; union types, intersection types, and readonly properties are not supported",
        );
    }
}
