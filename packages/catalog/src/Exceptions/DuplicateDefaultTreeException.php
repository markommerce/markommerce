<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class DuplicateDefaultTreeException extends MarkoException
{
    public static function forCode(string $code): self
    {
        return new self(
            message: "Category tree '$code' cannot be marked as default because another default tree already exists",
            context: "While attempting to set tree '$code' as the default",
            suggestion: 'Remove the default flag from the existing default tree before assigning a new one',
        );
    }
}
