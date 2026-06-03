<?php

declare(strict_types=1);

namespace Markommerce\Pricing\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Markommerce\Pricing\PriceContext;

class PriceUnavailableException extends MarkoException
{
    public static function forContext(PriceContext $context): self
    {
        $sku = $context->product->sku;
        $market = $context->market ?? 'global';

        return new self(
            message: "No resolvable price for product \"$sku\" in market \"$market\".",
            context: "Resolving price for SKU \"$sku\" in market \"$market\" — no price amount is set.",
            suggestion: 'Ensure the product has a priceAmount set, or that market-scoped overrides carry a price.',
        );
    }
}
