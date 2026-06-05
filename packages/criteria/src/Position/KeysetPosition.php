<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Position;

readonly class KeysetPosition
{
    /**
     * @param array<string, scalar> $anchor
     */
    public function __construct(
        public array $anchor,
        public int $id,
    ) {
    }

    public function type(): string
    {
        return 'keyset';
    }
}
