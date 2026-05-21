<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class CategoryNotFoundException extends MarkoException
{
    public static function forId(int $id): self
    {
        return new self(
            message: "Category with ID $id not found",
            context: 'While loading category',
            suggestion: 'Verify the category ID exists and is not archived',
        );
    }
}
