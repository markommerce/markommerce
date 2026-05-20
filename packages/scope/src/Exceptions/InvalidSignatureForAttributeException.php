<?php

declare(strict_types=1);

namespace Markommerce\Scope\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when a well-formed signature is invalid for a specific attribute's declared axes.
 */
class InvalidSignatureForAttributeException extends MarkoException
{
    /**
     * @param list<string> $allowedAxes
     */
    public static function forUnknownAxis(
        string $axis,
        array $allowedAxes,
    ): self
    {
        $allowed = implode(', ', $allowedAxes);

        return new self(
            message: "Signature axis '$axis' is not declared for this attribute; allowed axes: [$allowed]",
            context: "Validating ScopeSignature against attribute's declared axes",
            suggestion: "Remove axis '$axis' from the signature, or add it to the attribute's axes list",
        );
    }

    public static function forUnknownValue(
        string $value,
        string $axis,
    ): self
    {
        return new self(
            message: "Signature value '$value' for axis '$axis' does not exist in the registry hierarchy",
            context: "Validating ScopeSignature axis '$axis' value '$value' against the registry hierarchy",
            suggestion: "Ensure the value '$value' is declared in the hierarchy for axis '$axis'",
        );
    }

    public static function forDefaultScope(
        string $axis,
        string $defaultScope,
    ): self
    {
        return new self(
            message: "Signature names axis '$axis' at its default scope '$defaultScope'; the base column already holds the default value",
            context: "Validating ScopeSignature axis '$axis' value '$defaultScope' against the axis default",
            suggestion: "Remove the '$axis' axis from the signature, or edit the base column property directly — the base column already holds the default value for axis '$axis'",
        );
    }
}
