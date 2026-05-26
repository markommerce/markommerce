<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class DefaultTreeMissingException extends MarkoException
{
    public static function forResolution(): self
    {
        return new self(
            message: 'No default category tree is configured',
            context: 'While resolving the default category tree',
            suggestion: 'Mark exactly one category tree as the default in your tree configuration',
        );
    }
}
