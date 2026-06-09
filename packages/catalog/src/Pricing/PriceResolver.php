<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Pricing;

use Markommerce\Catalog\Pricing\Contracts\BatchPriceResolverInterface;
use Markommerce\Catalog\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Catalog\Pricing\Exceptions\PriceUnavailableException;
use Markommerce\Money\Money;

class PriceResolver implements PriceResolverInterface
{
    public function __construct(
        private BatchPriceResolverInterface $batchPriceResolver,
    ) {}

    /**
     * @throws PriceUnavailableException
     */
    public function resolve(PriceContext $context): Money
    {
        $result = $this->batchPriceResolver->resolve([0 => $context->product]);

        return $result[0] ?? throw PriceUnavailableException::forContext($context);
    }
}
