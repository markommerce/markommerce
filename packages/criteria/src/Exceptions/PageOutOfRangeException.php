<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class PageOutOfRangeException extends MarkoException
{
    public static function forPage(
        int $requested,
        int $totalPages,
    ): self {
        return new self(
            message: "Page $requested is out of range; total pages: $totalPages",
            context: 'While resolving a page number against the total page count',
            suggestion: 'Request a page number between 1 and the total number of pages',
        );
    }
}
