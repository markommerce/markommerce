<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Service;

use Markommerce\Catalog\Entity\Product;
use Markommerce\Money\MoneyFactoryInterface;
use Markommerce\Money\MoneyInterface;

/**
 * @todo multi-store: currency resolution will become store-scoped when the stores/config module lands.
 *   The MoneyFactory will be rebindable (Preference) to inject store-scope currency.
 */
class ProductPriceService implements ProductPriceServiceInterface
{
    public function __construct(
        private MoneyFactoryInterface $moneyFactory,
    ) {
    }

    public function getBasePrice(Product $product): MoneyInterface
    {
        return $this->moneyFactory->create($product->basePriceAmount);
    }
}
