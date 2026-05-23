<?php

declare(strict_types=1);

namespace Markommerce\Layout\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class IteratesOver
{
    /**
     * @param class-string $itemType
     */
    public function __construct(
        public string $itemType,
    ) {}
}
