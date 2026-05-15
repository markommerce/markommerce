<?php

declare(strict_types=1);

namespace Markommerce\Frontend\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class ViteHelperException extends MarkoException
{
    public static function emptyEntry(): self
    {
        return new self(
            message: 'The vite() template function requires a non-empty entry argument.',
            context: 'An empty string was passed as the entry argument to vite().',
            suggestion: 'Pass a non-empty entry path such as "resources/js/app.ts", or omit the argument to use the configured default entry.',
        );
    }
}
