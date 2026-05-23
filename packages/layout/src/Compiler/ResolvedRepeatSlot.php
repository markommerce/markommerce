<?php

declare(strict_types=1);

namespace Markommerce\Layout\Compiler;

class ResolvedRepeatSlot
{
    /**
     * @param list<ResolvedPlace> $children
     */
    public function __construct(
        public string $dataKey,
        public string $yields,
        public string $as,
        public array $children,
    ) {}
}
