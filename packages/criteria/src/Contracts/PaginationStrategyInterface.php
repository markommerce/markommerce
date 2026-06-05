<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Contracts;

use Marko\Database\Entity\Entity;
use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Criteria\Exceptions\IncompatiblePositionException;
use Markommerce\Criteria\Page\Page;
use Markommerce\Criteria\Page\PageRequest;

/**
 * @template TEntity of Entity
 */
interface PaginationStrategyInterface
{
    /**
     * @param RepositoryQueryBuilder $query
     * @return Page<TEntity>
     * @throws IncompatiblePositionException
     */
    public function paginate(
        RepositoryQueryBuilder $query,
        PageRequest $pageRequest,
        ?CursorValueExtractorInterface $cursorValueExtractor = null,
    ): Page;
}
