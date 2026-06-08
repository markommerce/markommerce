<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Strategy;

use InvalidArgumentException;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Criteria\Contracts\CursorValueExtractorInterface;
use Markommerce\Criteria\Contracts\PaginationStrategyInterface;
use Markommerce\Criteria\Exceptions\IncompatiblePositionException;
use Markommerce\Criteria\Exceptions\MissingCursorValueExtractorException;
use Markommerce\Criteria\Page\Page;
use Markommerce\Criteria\Page\PageRequest;
use Markommerce\Criteria\Position\KeysetPosition;
use Markommerce\Criteria\Position\OffsetPosition;
use Markommerce\Criteria\Position\PositionCodec;

/**
 * Keyset (seek) pagination strategy.
 *
 * Applies a WHERE row-value seek predicate, orders deterministically (sort
 * fields + id tie-break), fetches size + 1 to detect a next page, and encodes
 * next/previous anchors from the boundary entity's properties via the
 * caller-supplied CursorValueExtractorInterface.
 *
 * Sort keys and the id column must be addressable on the hydrated entity.
 *
 * @implements PaginationStrategyInterface<Entity>
 */
class KeysetPaginationStrategy implements PaginationStrategyInterface
{
    public function __construct(
        private readonly PositionCodec $positionCodec,
    ) {}

    /**
     * @return Page<Entity>
     * @throws IncompatiblePositionException|MissingCursorValueExtractorException
     */
    public function paginate(
        RepositoryQueryBuilder $query,
        PageRequest $pageRequest,
        ?CursorValueExtractorInterface $cursorValueExtractor = null,
    ): Page {
        $position = null;

        if ($pageRequest->position !== null) {
            $decoded = $this->positionCodec->decode($pageRequest->position);

            if ($decoded instanceof OffsetPosition) {
                throw IncompatiblePositionException::expected(self::class, 'offset');
            }

            $position = $decoded;
        }

        if ($position instanceof KeysetPosition) {
            $this->applySeekPredicate($query, $position);
        }

        foreach ($pageRequest->sort->fields as $field) {
            $query->orderBy($field->column, $field->direction->value);
        }
        $query->orderBy('id', 'ASC');

        $query->limit($pageRequest->size + 1);

        /** @var array<int, Entity> $items */
        $items = $query->getEntities()->toArray();

        $hasNext = count($items) > $pageRequest->size;

        if ($hasNext) {
            array_pop($items);
        }

        $nextPosition = null;

        if ($hasNext && $items !== []) {
            if ($cursorValueExtractor === null) {
                throw MissingCursorValueExtractorException::forKeyset();
            }

            $lastEntity = $items[count($items) - 1];
            $anchorValues = $cursorValueExtractor->extract($lastEntity, $pageRequest->sort);
            $idValue = (int) ($lastEntity->id ?? 0);
            $keysetPosition = new KeysetPosition(anchor: $anchorValues, id: $idValue);
            $nextPosition = $this->positionCodec->encode($keysetPosition);
        }

        return new Page(
            items: new EntityCollection($items),
            size: $pageRequest->size,
            nextPosition: $nextPosition,
            previousPosition: null,
        );
    }

    /**
     * Apply the multi-column seek predicate using a PostgreSQL row-value comparison.
     *
     * Column identifiers from the anchor are validated against /^[a-zA-Z_][a-zA-Z0-9_]*$/
     * before interpolation. The 'id' tie-break column is appended unconditionally.
     *
     * Trust boundary: callers must supply validated sort-key column names.
     * Dynamic values are bound as parameters, never interpolated.
     */
    private function applySeekPredicate(RepositoryQueryBuilder $query, KeysetPosition $position): void
    {
        $anchor = $position->anchor;
        $id = $position->id;

        $columns = array_keys($anchor);
        $values = array_values($anchor);
        $values[] = $id;

        $safeColumns = array_map(
            static function (string $column): string {
                if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column) !== 1) {
                    throw new InvalidArgumentException(
                        "Sort key column '$column' contains invalid characters for a SQL identifier",
                    );
                }

                return $column;
            },
            $columns,
        );

        $columnList = implode(', ', $safeColumns) . ', id';
        $placeholders = implode(', ', array_fill(0, count($columns) + 1, '?'));

        $query->whereRaw("($columnList) > ($placeholders)", $values);
    }
}
