<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Position;

readonly class OffsetPosition
{
    public function __construct(
        public int $page,
    ) {
    }

    public function type(): string
    {
        return 'offset';
    }
}
