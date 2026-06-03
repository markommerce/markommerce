<?php

declare(strict_types=1);

namespace Markommerce\Pricing;

use Markommerce\Catalog\Entity\Product;

/**
 * Describes what is being priced.
 *
 * For per-market price resolution to work correctly, the `Product` passed here
 * must carry its `ProductScopedOverrides` companion (i.e. it was loaded via the
 * repository with extenders linked). A bare `new Product()` will only resolve
 * the global `priceAmount`.
 *
 * Future fields intended for extension: `qty`, `customerGroup`, `date`.
 * These are not present yet — they will be added as dedicated requirements.
 */
readonly class PriceContext
{
    private function __construct(
        public Product $product,
        public ?string $market = null,
    ) {}

    public static function forProduct(
        Product $product,
        ?string $market = null,
    ): self
    {
        return new self($product, $market);
    }
}
