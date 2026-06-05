<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class InvalidPageSizeException extends MarkoException
{
    public static function forSize(int $size): self
    {
        return new self(
            message: "Page size must be greater than zero, got $size",
            context: 'While constructing a PageRequest value object',
            suggestion: 'Pass a positive integer as the size argument',
        );
    }
}
