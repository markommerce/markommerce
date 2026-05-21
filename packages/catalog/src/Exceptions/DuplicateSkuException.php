<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class DuplicateSkuException extends MarkoException
{
    public static function forSku(string $sku): self
    {
        return new self(
            message: "A product with SKU '$sku' already exists",
            context: 'While creating or updating a product',
            suggestion: 'Use a unique SKU or update the existing product with that SKU instead',
        );
    }
}
