<?php

declare(strict_types=1);

namespace Markommerce\Scope\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when scope configuration is malformed or invalid.
 */
class ScopeConfigurationException extends MarkoException
{
    public static function malformedString(string $scope): self
    {
        return new self(
            message: "Malformed scope string \"$scope\": expected \"axis:path\" format",
            context: "Parsing scope string \"$scope\" via Scope::fromString()",
            suggestion: 'Provide a scope string in the format "axisName:path", e.g. "geo:eu.de"',
        );
    }

    public static function malformedConfig(
        string $axis,
        string $reason,
    ): self {
        return new self(
            message: "Scope configuration for axis '$axis' is malformed: $reason",
            context: "Parsing scope configuration for axis '$axis'",
            suggestion: "Review the scope configuration for axis '$axis' and correct the malformed definition",
        );
    }

    public static function duplicatePath(string $path): self
    {
        return new self(
            message: "Duplicate scope path '$path' declared in hierarchy",
            context: "Building ScopeHierarchy with path '$path' that has already been declared",
            suggestion: "Remove the duplicate declaration of '$path' from the scope configuration",
        );
    }

    public static function missingDefault(string $axis): self
    {
        return new self(
            message: "Scope axis '$axis' is missing a 'default' scope declaration",
            context: "Building scope axis '$axis' from configuration",
            suggestion: "Add a 'default' key to the '$axis' axis configuration naming the root/global scope path (e.g. 'default: global')",
        );
    }

    public static function defaultNotInScopes(
        string $axis,
        string $default,
    ): self {
        return new self(
            message: "Default scope '$default' for axis '$axis' is not declared in the axis scopes map",
            context: "Validating scope axis '$axis' configuration — the declared default '$default' was not found among the registered scope paths",
            suggestion: "Either add '$default' to the '$axis' scopes map or change the 'default' value to one of the already-declared scope paths",
        );
    }

    public static function emptyScopesMap(string $axis): self
    {
        return new self(
            message: "Scope axis '$axis' declares an empty scopes map — at least one scope path is required",
            context: "Building scope axis '$axis' from configuration",
            suggestion: "Add at least one scope path to the '$axis' axis 'scopes' map and declare which path is the 'default'",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function missingScopesStorage(string $entityClass): self
    {
        return new self(
            message: "Entity '$entityClass' has #[Scoped] properties but provides no scope storage. Add 'use HasScopes; implements HasScopesInterface;' to the entity, or register a companion class that implements HasScopesInterface.",
            context: "Validating scoped entity '$entityClass'",
            suggestion: "Either add 'use HasScopes; implements HasScopesInterface;' to '$entityClass', or create and register a companion class extending Entity that implements HasScopesInterface using the HasScopes trait.",
        );
    }
}
