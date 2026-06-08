<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class PageDepthExceededException extends MarkoException
{
    public static function forDepth(
        int $requested,
        int $maxDepth,
    ): self {
        return new self(
            message: "Requested page $requested exceeds the maximum allowed page depth of $maxDepth",
            context: 'While resolving pagination page number',
            suggestion: "Request a page number no greater than $maxDepth, or increase catalog/pagination.maxPageDepth in config",
        );
    }
}
