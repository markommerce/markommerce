<?php

declare(strict_types=1);

namespace Markommerce\CatalogStorefront\Component;

use Markommerce\CatalogStorefront\Data\StockBadgeData;
use Markommerce\Layout\ExtensionBag;

class StockBadge
{
    public function data(bool $inStock): StockBadgeData
    {
        return new StockBadgeData(
            inStock: $inStock,
            extensions: new ExtensionBag(),
        );
    }
}
