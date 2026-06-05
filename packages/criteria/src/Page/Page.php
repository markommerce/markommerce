<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Page;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;

/**
 * @template TEntity of Entity
 */
readonly class Page
{
    /**
     * @param EntityCollection<TEntity> $items
     */
    public function __construct(
        public EntityCollection $items,
        public int $size,
        public ?string $nextPosition,
        public ?string $previousPosition,
    ) {}

    public function hasNext(): bool
    {
        return $this->nextPosition !== null;
    }

    public function hasPrevious(): bool
    {
        return $this->previousPosition !== null;
    }
}
