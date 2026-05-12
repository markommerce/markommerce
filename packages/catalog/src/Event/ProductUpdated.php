<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Event;

use Marko\Core\Event\Event;
use Markommerce\Catalog\Entity\Product;

class ProductUpdated extends Event
{
    public function __construct(public readonly Product $product) {}
}
