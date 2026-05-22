<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Iteration;

use Markommerce\Layout\Attributes\IteratesOver;
use Markommerce\LayoutDemo\Entity\Item;

#[IteratesOver(Item::class)]
class ItemIteration {}
