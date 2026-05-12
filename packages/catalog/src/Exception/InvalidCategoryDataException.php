<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Exception;

use Marko\Core\Exceptions\MarkoException;

class InvalidCategoryDataException extends MarkoException
{
    public static function emptyName(): self
    {
        return new self(
            message: 'Category name must not be empty',
            context: 'While validating category data',
            suggestion: 'Provide a non-empty name for the category',
        );
    }
}
