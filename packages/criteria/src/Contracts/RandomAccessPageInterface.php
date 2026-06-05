<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Contracts;

use Markommerce\Criteria\Exceptions\PageOutOfRangeException;

/**
 * @template TEntity of object
 */
interface RandomAccessPageInterface
{
    public function currentPage(): int;

    public function totalPages(): int;

    public function totalItems(): int;

    /**
     * @throws PageOutOfRangeException
     */
    public function positionForPage(int $page): string;
}
