<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Iteration;

use Markommerce\Catalog\Entity\Product;
use Markommerce\Layout\Attributes\IteratesOver;

#[IteratesOver(Product::class)]
class ProductIteration {}
