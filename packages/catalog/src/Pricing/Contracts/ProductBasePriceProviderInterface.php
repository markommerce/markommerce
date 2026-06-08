<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Pricing\Contracts;

use Markommerce\Catalog\Entity\Product;

interface ProductBasePriceProviderInterface
{
    /**
     * @param array<array-key, Product> $products  caller-keyed map
     * @return array<array-key, ?string>           same keys → raw decimal amount or null
     */
    public function amountsFor(array $products): array;
}
