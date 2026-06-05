<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Strategy;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Markommerce\Criteria\Contracts\RandomAccessPageInterface;
use Markommerce\Criteria\Exceptions\PageOutOfRangeException;
use Markommerce\Criteria\Page\Page;
use Markommerce\Criteria\Position\OffsetPosition;
use Markommerce\Criteria\Position\PositionCodec;

/**
 * @template TEntity of Entity
 * @extends Page<TEntity>
 * @implements RandomAccessPageInterface<TEntity>
 */
readonly class OffsetPage extends Page implements RandomAccessPageInterface
{
    /**
     * @param EntityCollection<TEntity> $items
     */
    public function __construct(
        EntityCollection $items,
        int $size,
        ?string $nextPosition,
        ?string $previousPosition,
        private int $currentPage,
        private int $totalPages,
        private int $totalItems,
        private PositionCodec $positionCodec,
    ) {
        parent::__construct(
            items: $items,
            size: $size,
            nextPosition: $nextPosition,
            previousPosition: $previousPosition,
        );
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function totalPages(): int
    {
        return $this->totalPages;
    }

    public function totalItems(): int
    {
        return $this->totalItems;
    }

    /**
     * @throws PageOutOfRangeException
     */
    public function positionForPage(int $page): string
    {
        if ($page < 1 || $page > $this->totalPages) {
            throw PageOutOfRangeException::forPage($page, $this->totalPages);
        }

        return $this->positionCodec->encode(new OffsetPosition(page: $page));
    }
}
