<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Exceptions;

class UnknownSortRequestedException extends InvalidPaginationConfigException
{
    public static function forRequestedKey(
        string $sort,
        string $allowedKeys,
    ): self
    {
        return new self(
            message: "Sort '$sort' is not in the allowed list",
            context: 'While resolving pagination sort from request',
            suggestion: "Use one of the allowed sort keys: $allowedKeys",
        );
    }
}
