<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Contracts;

use Markommerce\Criteria\Sort\Sort;

interface CursorValueExtractorInterface
{
    /**
     * @return array<string, scalar>
     */
    public function extract(
        object $entity,
        Sort $sort,
    ): array;
}
