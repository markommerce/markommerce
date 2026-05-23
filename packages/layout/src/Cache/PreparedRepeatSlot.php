<?php

declare(strict_types=1);

namespace Markommerce\Layout\Cache;

readonly class PreparedRepeatSlot
{
    /**
     * @param list<PreparedPlace> $children
     */
    public function __construct(
        public string $dataKey,
        public string $yields,
        public string $as,
        public array $children,
    ) {}
}
