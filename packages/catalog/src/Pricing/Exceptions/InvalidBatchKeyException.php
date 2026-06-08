<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Pricing\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class InvalidBatchKeyException extends MarkoException
{
    public static function forKey(int|string $key): self
    {
        return new self(
            message: "Key '$key' is not present in the price batch",
            context: 'While setting amount on PriceBatch',
            suggestion: 'Contributors must only set amounts for keys that belong to this batch',
        );
    }
}
