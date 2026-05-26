<?php

declare(strict_types=1);

namespace Markommerce\CatalogStorefront\Data;

use Markommerce\Layout\ExtensibleData;
use Markommerce\Layout\ExtensionBag;

readonly class StockBadgeData extends ExtensibleData
{
    public function __construct(
        public bool $inStock,
        ExtensionBag $extensions = new ExtensionBag(),
    ) {
        parent::__construct($extensions);
    }
}
