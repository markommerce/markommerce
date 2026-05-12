<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Event;

use Marko\Core\Event\Event;
use Markommerce\Catalog\Entity\Category;

class CategoryUpdated extends Event
{
    public function __construct(public readonly Category $category) {}
}
