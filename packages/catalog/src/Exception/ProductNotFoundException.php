<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Exception;

use Marko\Core\Exceptions\MarkoException;

class ProductNotFoundException extends MarkoException
{
    public static function forId(int $id): self
    {
        return new self(
            message: "Product with ID {$id} not found",
            context: 'While loading product by ID',
            suggestion: 'Verify the product ID exists in the catalog',
        );
    }

    public static function forSku(string $sku): self
    {
        return new self(
            message: "Product with SKU \"{$sku}\" not found",
            context: 'While loading product by SKU',
            suggestion: 'Verify the SKU exists in the catalog',
        );
    }
}
