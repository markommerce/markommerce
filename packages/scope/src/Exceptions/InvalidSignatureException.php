<?php

declare(strict_types=1);

namespace Markommerce\Scope\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when a scope signature string or array is malformed or invalid.
 */
class InvalidSignatureException extends MarkoException
{
    public static function emptyArray(): self
    {
        return new self(
            message: 'A ScopeSignature cannot be constructed from an empty array',
            context: 'Constructing ScopeSignature from an associative array',
            suggestion: 'Provide at least one axis-to-value pair, e.g. ["channel" => "b2b"]',
        );
    }

    public static function emptyAxis(): self
    {
        return new self(
            message: 'Axis name must not be empty',
            context: 'Constructing ScopeSignature from an associative array',
            suggestion: 'Ensure every key in the axes array is a non-empty string',
        );
    }

    public static function emptyValue(string $axis): self
    {
        return new self(
            message: "Value for axis '$axis' must not be empty",
            context: "Constructing ScopeSignature with axis '$axis'",
            suggestion: "Provide a non-empty string value for axis '$axis'",
        );
    }

    public static function emptyString(): self
    {
        return new self(
            message: 'A ScopeSignature cannot be parsed from an empty string',
            context: 'Parsing ScopeSignature via ScopeSignature::fromString()',
            suggestion: 'Provide a non-empty signature string, e.g. "channel:b2b" or "channel:b2b|locale:es"',
        );
    }

    public static function malformedString(string $signature): self
    {
        return new self(
            message: "Malformed signature string \"$signature\": each part must be in \"axis:value\" format with exactly one colon",
            context: "Parsing ScopeSignature via ScopeSignature::fromString(\"$signature\")",
            suggestion: 'Provide a signature string like "channel:b2b" or "channel:b2b|locale:es"',
        );
    }

    public static function duplicateAxis(
        string $axis,
        string $signature,
    ): self {
        return new self(
            message: "Duplicate axis '$axis' found in signature string \"$signature\"",
            context: "Parsing ScopeSignature via ScopeSignature::fromString(\"$signature\")",
            suggestion: "Remove duplicate axis '$axis' from the signature string",
        );
    }
}
