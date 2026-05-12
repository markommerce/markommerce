<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Event;

use Marko\Core\Event\Event;

class ProductRemovedFromCategory extends Event
{
    public function __construct(
        public readonly int $productId,
        public readonly int $categoryId,
    ) {}
}
