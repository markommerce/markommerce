<?php

declare(strict_types=1);

namespace Markommerce\Layout\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class ProvidesHandles
{
    /**
     * @param list<string> $handles
     */
    public function __construct(
        public array $handles,
    ) {}
}
