<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class CategoryHasPlacementsException extends MarkoException
{
    public static function forCategory(int $categoryId, int $placementCount): self
    {
        return new self(
            message: "Cannot delete category $categoryId: it still has $placementCount placement(s)",
            context: "While deleting category $categoryId",
            suggestion: 'Remove all category placements before deleting the category',
        );
    }
}
