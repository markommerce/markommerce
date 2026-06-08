<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Pricing;

use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pricing\Contracts\ProductBasePriceProviderInterface;

class RawProductBasePriceProvider implements ProductBasePriceProviderInterface
{
    /**
     * @param array<array-key, Product> $products
     * @return array<array-key, ?string>
     */
    public function amountsFor(array $products): array
    {
        return array_map(fn (Product $p) => $p->priceAmount, $products);
    }
}
