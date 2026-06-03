<?php

declare(strict_types=1);

namespace Markommerce\Pricing\Contracts;

use Markommerce\Money\Money;
use Markommerce\Pricing\Exceptions\PriceUnavailableException;
use Markommerce\Pricing\PriceContext;

/**
 * Resolves a product price for a given context into a Money value.
 *
 * This interface is the primary Marko `#[Plugin]` decoration seam for the pricing
 * pipeline. Sale prices, tier prices, customer-group discounts, and promotional
 * overrides are all layered in by decorating implementations via Marko plugins —
 * no concrete plugin is built here; this contract merely establishes the boundary.
 */
interface PriceResolverInterface
{
    /**
     * @throws PriceUnavailableException
     */
    public function resolve(PriceContext $context): Money;
}
