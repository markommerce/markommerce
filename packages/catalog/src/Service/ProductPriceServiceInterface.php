<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Service;

use Markommerce\Catalog\Entity\Product;
use Markommerce\Money\MoneyInterface;

interface ProductPriceServiceInterface
{
    public function getBasePrice(Product $product): MoneyInterface;
}
