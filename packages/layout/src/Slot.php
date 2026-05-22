<?php

declare(strict_types=1);

namespace Markommerce\Layout;

readonly class Slot
{
    /**
     * @param list<Place> $children
     */
    private function __construct(
        public string $dataKey,
        public string $yields,
        public string $as,
        public array $children,
    ) {}

    /**
     * @param list<Place> $children
     */
    public static function repeat(string $dataKey, string $yields, string $as, array $children): self
    {
        return new self($dataKey, $yields, $as, $children);
    }
}
