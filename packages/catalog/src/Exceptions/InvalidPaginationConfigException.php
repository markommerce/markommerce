<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class InvalidPaginationConfigException extends MarkoException
{
    public static function forUnsupportedCountMode(string $mode): self
    {
        return new self(
            message: "Unsupported count mode '$mode'",
            context: 'While resolving pagination options from config',
            suggestion: "Set catalog/pagination.countMode to one of: 'exact', 'estimated'",
        );
    }

    public static function forUnknownPresentation(string $presentation): self
    {
        return new self(
            message: "Unknown pagination presentation '$presentation'",
            context: 'While resolving pagination options from config',
            suggestion: "Set catalog/pagination.presentation to one of: 'numbered', 'load_more', 'infinite'",
        );
    }

    public static function forUnknownStrategy(string $strategy): self
    {
        return new self(
            message: "Unknown pagination strategy '$strategy'",
            context: 'While resolving pagination options from config',
            suggestion: "Set catalog/pagination.strategy to one of: 'offset', 'keyset'",
        );
    }

    public static function forInvalidSort(
        string $sort,
        string $allowed,
    ): self {
        return new self(
            message: "Sort '$sort' is not in the allowed list",
            context: 'While resolving pagination sort from request',
            suggestion: "Use one of the allowed sort keys: $allowed",
        );
    }

    public static function forNumberedKeysetCombination(): self
    {
        return new self(
            message: "Pagination presentation 'numbered' is incompatible with strategy 'keyset'",
            context: 'While validating pagination strategy and presentation combination',
            suggestion: "Use 'offset' strategy with 'numbered' presentation, or switch presentation to 'load_more' or 'infinite' for keyset",
        );
    }

    public static function forKeysetIncompatibleSort(string $sortKey): self
    {
        return new self(
            message: "Sort order '$sortKey' does not support keyset (cursor-based) pagination",
            context: 'While resolving pagination options with keyset strategy',
            suggestion: "Register the sort order with supportsKeyset: true, or switch the pagination strategy to 'offset'",
        );
    }
}
