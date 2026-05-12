<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Exception;

use Marko\Core\Exceptions\MarkoException;

class CategoryNotFoundException extends MarkoException
{
    public static function forId(int $id): self
    {
        return new self(
            message: "Category with ID {$id} not found",
            context: 'While loading category by ID',
            suggestion: 'Verify the category ID exists in the catalog',
        );
    }
}
