<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Pricing\Contracts;

use Markommerce\Catalog\Entity\Product;
use Markommerce\Money\Money;

interface BatchPriceResolverInterface
{
    /**
     * @param array<array-key, Product> $products
     * @return array<array-key, Money> only keys with a non-null resolved amount; null-amount keys omitted
     */
    public function resolve(array $products): array;
}
