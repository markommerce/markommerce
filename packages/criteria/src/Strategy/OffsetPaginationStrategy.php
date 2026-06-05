<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Strategy;

use Marko\Database\Entity\Entity;
use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Criteria\Contracts\CursorValueExtractorInterface;
use Markommerce\Criteria\Contracts\PaginationStrategyInterface;
use Markommerce\Criteria\Contracts\RowCounterInterface;
use Markommerce\Criteria\Exceptions\IncompatiblePositionException;
use Markommerce\Criteria\Page\Page;
use Markommerce\Criteria\Page\PageRequest;
use Markommerce\Criteria\Position\KeysetPosition;
use Markommerce\Criteria\Position\OffsetPosition;
use Markommerce\Criteria\Position\PositionCodec;

/**
 * @implements PaginationStrategyInterface<Entity>
 */
class OffsetPaginationStrategy implements PaginationStrategyInterface
{
    public function __construct(
        private readonly PositionCodec $positionCodec,
        private readonly RowCounterInterface $rowCounter,
    ) {}

    /**
     * @throws IncompatiblePositionException
     * @return Page<Entity>
     */
    public function paginate(
        RepositoryQueryBuilder $query,
        PageRequest $pageRequest,
        ?CursorValueExtractorInterface $cursorValueExtractor = null,
    ): Page {
        $currentPage = $this->resolveCurrentPage($pageRequest);
        $size = $pageRequest->size;

        // Get the total BEFORE applying limit/offset (query builder mutates in place).
        $total = $this->rowCounter->count($query);
        $totalPages = max(1, (int) ceil($total / $size));

        // Apply sort fields + deterministic tie-break.
        foreach ($pageRequest->sort->fields as $field) {
            $query->orderBy($field->column, $field->direction->value);
        }
        $query->orderBy('id', 'ASC');

        // Apply offset and fetch size+1 rows to detect hasNext.
        $offset = ($currentPage - 1) * $size;
        $query->offset($offset)->limit($size + 1);

        $allItems = $query->getEntities()->toArray();
        $hasNext = count($allItems) > $size;
        $items = array_slice($allItems, 0, $size);

        $nextPosition = ($hasNext)
            ? $this->positionCodec->encode(new OffsetPosition(page: $currentPage + 1))
            : null;

        $previousPosition = ($currentPage > 1)
            ? $this->positionCodec->encode(new OffsetPosition(page: $currentPage - 1))
            : null;

        /** @var \Marko\Database\Entity\EntityCollection<Entity> $itemsCollection */
        $itemsCollection = new \Marko\Database\Entity\EntityCollection($items);

        return new OffsetPage(
            items: $itemsCollection,
            size: $size,
            nextPosition: $nextPosition,
            previousPosition: $previousPosition,
            currentPage: $currentPage,
            totalPages: $totalPages,
            totalItems: $total,
            positionCodec: $this->positionCodec,
        );
    }

    /**
     * @throws IncompatiblePositionException
     */
    private function resolveCurrentPage(PageRequest $pageRequest): int
    {
        if ($pageRequest->position === null) {
            return 1;
        }

        $position = $this->positionCodec->decode($pageRequest->position);

        if ($position instanceof KeysetPosition) {
            throw IncompatiblePositionException::expected(
                strategy: self::class,
                tokenType: 'keyset',
            );
        }

        /** @var OffsetPosition $position */
        return $position->page;
    }
}
