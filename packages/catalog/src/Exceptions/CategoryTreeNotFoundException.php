<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class CategoryTreeNotFoundException extends MarkoException
{
    public static function forId(int $id): self
    {
        return new self(
            message: "Category tree with ID $id not found",
            context: 'While loading category tree by ID',
            suggestion: 'Verify the category tree ID exists and has not been deleted',
        );
    }

    public static function forCode(string $code): self
    {
        return new self(
            message: "Category tree with code '$code' not found",
            context: 'While loading category tree by code',
            suggestion: "Verify the category tree code '$code' exists and has not been deleted",
        );
    }
}
