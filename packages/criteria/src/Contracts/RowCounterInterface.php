<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Contracts;

use Marko\Database\Repository\RepositoryQueryBuilder;

interface RowCounterInterface
{
    public function count(RepositoryQueryBuilder $query): int;
}
