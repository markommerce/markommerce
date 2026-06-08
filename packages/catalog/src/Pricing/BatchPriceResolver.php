<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Pricing;

use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pricing\Contracts\BatchPriceResolverInterface;
use Markommerce\Currency\CurrencyResolver;
use Markommerce\Money\Money;

class BatchPriceResolver implements BatchPriceResolverInterface
{
    public function __construct(
        private PriceContributorRegistry $registry,
        private CurrencyResolver $currencyResolver,
    ) {}

    /**
     * @param array<array-key, Product> $products
     * @return array<array-key, Money>
     */
    public function resolve(array $products): array
    {
        $currency = $this->currencyResolver->base();
        $batch    = PriceBatch::of($products, $currency);

        foreach ($this->registry->all() as $contributor) {
            $contributor->contribute($batch);
        }

        $result = [];

        foreach ($batch->keys() as $key) {
            $amount = $batch->amount($key);

            if ($amount !== null) {
                $result[$key] = Money::of($amount, $currency);
            }
        }

        return $result;
    }
}
