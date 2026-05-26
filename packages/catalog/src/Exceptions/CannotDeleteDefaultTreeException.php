<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class CannotDeleteDefaultTreeException extends MarkoException
{
    public static function forTreeId(int $treeId): self
    {
        return new self(
            message: "Category tree with ID $treeId cannot be deleted because it is the default tree",
            context: "While attempting to delete category tree with ID $treeId",
            suggestion: 'Assign a different tree as the default before deleting this one',
        );
    }
}
