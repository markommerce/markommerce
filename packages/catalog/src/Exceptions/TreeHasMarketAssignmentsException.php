<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class TreeHasMarketAssignmentsException extends MarkoException
{
    /** @param array<string> $markets */
    public static function forTreeId(int $treeId, array $markets): self
    {
        $marketList = implode(', ', $markets);

        return new self(
            message: "Category tree with ID $treeId cannot be deleted because it is still assigned to markets: $marketList",
            context: "While attempting to delete category tree with ID $treeId",
            suggestion: 'Reassign all listed markets to a different category tree before deleting this one',
        );
    }
}
