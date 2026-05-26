<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class CategoryTreeNodeNotFoundException extends MarkoException
{
    public static function forId(int $id): self
    {
        return new self(
            message: "Category tree node with ID $id not found",
            context: 'While loading category tree node',
            suggestion: 'Verify the category tree node ID exists and belongs to the expected tree',
        );
    }
}
