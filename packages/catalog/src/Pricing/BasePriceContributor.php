<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Pricing;

use Markommerce\Catalog\Pricing\Contracts\PriceContributorInterface;
use Markommerce\Catalog\Pricing\Contracts\ProductBasePriceProviderInterface;

class BasePriceContributor implements PriceContributorInterface
{
    public function __construct(
        private ProductBasePriceProviderInterface $basePriceProvider,
    ) {}

    public function contribute(PriceBatch $batch): void
    {
        $amounts = $this->basePriceProvider->amountsFor($batch->products());

        foreach ($amounts as $key => $amount) {
            $batch->setAmount($key, $amount);
        }
    }
}
