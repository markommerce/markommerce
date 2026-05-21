<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class ProductNotFoundException extends MarkoException
{
    public static function forId(int $id): self
    {
        return new self(
            message: "Product with ID $id not found",
            context: 'While loading product',
            suggestion: 'Verify the product ID exists and is not archived',
        );
    }
}
