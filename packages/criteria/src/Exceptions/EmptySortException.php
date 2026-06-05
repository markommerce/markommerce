<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class EmptySortException extends MarkoException
{
    public static function create(): self
    {
        return new self(
            message: 'A Sort must contain at least one SortField',
            context: 'While constructing a Sort value object',
            suggestion: 'Pass at least one SortField to the Sort constructor',
        );
    }
}
