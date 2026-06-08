<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Pricing\Contracts;

use Markommerce\Catalog\Pricing\PriceBatch;

interface PriceContributorInterface
{
    /**
     * Contribute pricing data to the batch set-wise.
     *
     * Implementors MUST issue a query count independent of the batch size (N+1-free):
     * load all data for the batch with one set-wise query keyed by the product ids,
     * then call setAmount for each key. Never query per-product.
     */
    public function contribute(PriceBatch $batch): void;
}
